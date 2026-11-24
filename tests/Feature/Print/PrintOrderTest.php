<?php

namespace Tests\Feature\Print;

use App\Models\AuthorEarning;
use App\Models\PrintOrder;
use App\Services\PrintService;
use InvalidArgumentException;

/**
 * Phase 4 spec §2 — manual print / POD: order → TrxID → verify →
 * confirmed → send to partner → printing → shipped → delivered,
 * author share on delivered, cancel rules.
 */
class PrintOrderTest extends PrintTestCase
{
    public function test_price_order_uses_book_price_then_settings_fallback(): void
    {
        $prints = app(PrintService::class);

        $priced = $prints->priceOrder($this->prophet, 2);

        $this->assertSame(454, $priced['unit_price_bdt']);
        $this->assertSame(0, $priced['delivery_charge_bdt']);
        $this->assertSame(908, $priced['total_bdt']);
        $this->assertSame('book', $priced['price_source']);

        // No book price + no settings price → order cannot be placed.
        $this->prophet->update(['print_price_bdt' => null]);
        $this->expectException(InvalidArgumentException::class);
        $prints->priceOrder($this->prophet->fresh(), 1);
    }

    public function test_full_flow_order_to_delivered(): void
    {
        $prints = app(PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());

        $this->assertSame('ordered', $order->status);
        $this->assertSame(454, $order->total_bdt);
        $this->assertMatchesRegularExpression('/^LV-P-\d{4}-\d{4}$/', $order->order_no);

        // TrxID submitted, still ordered — never to partner before verify.
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->assertSame('ordered', $order->fresh()->status);
        $this->assertSame('9HX7K2LMNP', $order->fresh()->payment_trx_id);

        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $this->assertSame('confirmed', $order->status);

        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);
        $this->assertSame('sent_to_partner', $order->status);
        $this->assertSame($this->partner->id, $order->partner_id);
        $this->assertSame(200, $order->partner_cost_bdt);

        $order = $prints->markPrinting($order->fresh(), $this->partner);
        $this->assertSame('printing', $order->status);

        $order = $prints->markShipped($order->fresh(), $this->partner, 'TRACK123');
        $this->assertSame('shipped', $order->status);
        $this->assertSame('TRACK123', $order->tracking_no);

        $order = $prints->markDelivered($order->fresh(), $this->admin);
        $this->assertSame('delivered', $order->status);
    }

    public function test_delivered_accrues_author_share_203_on_454_minus_200(): void
    {
        $order = $this->deliveredOrder();

        $earning = AuthorEarning::where('note', 'print order '.$order->order_no)->firstOrFail();

        $this->assertSame('sale', $earning->type);
        $this->assertSame($this->author->id, $earning->author_id);
        $this->assertSame($this->prophet->id, $earning->book_id);
        $this->assertSame(203, $earning->amount_bdt); // (454 − 200) × 80%
        $this->assertSame(80, $earning->share_pct);

        // Idempotent: a second accrual creates nothing.
        app(PrintService::class);
        app(\App\Services\PayoutService::class)->accruePrintSale($order->fresh());
        $this->assertSame(1, AuthorEarning::where('note', 'print order '.$order->order_no)->count());
    }

    public function test_no_share_before_delivered_or_without_partner_cost(): void
    {
        $prints = app(PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        app(\App\Services\PayoutService::class)->accruePrintSale($order->fresh());
        $this->assertSame(0, AuthorEarning::where('note', 'print order '.$order->order_no)->count());

        // Delivered with unknown partner cost (spec A4) → no share.
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, null, $this->admin);
        $order = $prints->markPrinting($order->fresh(), $this->partner);
        $order = $prints->markShipped($order->fresh(), $this->partner);
        $prints->markDelivered($order->fresh(), $this->admin);
        $this->assertSame(0, AuthorEarning::where('note', 'print order '.$order->order_no)->count());
    }

    public function test_buyer_can_cancel_before_confirmation_only(): void
    {
        $prints = app(PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        $order = $prints->cancel($order, $this->reader);
        $this->assertSame('cancelled', $order->status);

        // After confirmation the buyer can no longer cancel.
        $order2 = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        $prints->submitTrxId($order2, 'bkash', '9HX7K2LMNP');
        $order2 = $prints->onPaymentVerified($order2->fresh(), $this->admin);

        $this->expectException(InvalidArgumentException::class);
        $prints->cancel($order2->fresh(), $this->reader);
    }

    public function test_admin_can_cancel_after_sent_to_partner(): void
    {
        $prints = app(PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);

        $order = $prints->cancel($order->fresh(), $this->admin);
        $this->assertSame('cancelled', $order->status);
    }

    public function test_only_assigned_partner_can_move_the_order(): void
    {
        $prints = app(PrintService::class);
        $otherPartner = \App\Models\User::factory()->create(['phone' => '01700000014']);
        $otherPartner->assignRole('print-partner');

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);

        $this->expectException(InvalidArgumentException::class);
        $prints->markPrinting($order->fresh(), $otherPartner);
    }

    public function test_send_to_partner_requires_confirmed_and_partner_role(): void
    {
        $prints = app(PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());

        // Still ordered — cannot go to partner before payment verification.
        $this->expectException(InvalidArgumentException::class);
        $prints->sendToPartner($order, $this->partner, 200, $this->admin);
    }

    public function test_quantity_over_10_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(PrintService::class)->createOrder($this->reader, $this->prophet, $this->orderData(['quantity' => 11]));
    }

    public function test_web_flow_create_and_trxid_and_cancel(): void
    {
        $this->actingAs($this->reader);

        $response = $this->post('/bn/books/the-prophet/print-order', $this->orderData());
        $response->assertRedirect();

        $order = PrintOrder::firstOrFail();
        $this->assertSame('ordered', $order->status);

        $this->get('/bn/print-orders/'.$order->id)->assertOk();

        $this->post('/bn/print-orders/'.$order->id.'/trxid', ['method' => 'bkash', 'trx_id' => '9HX7K2LMNP'])
            ->assertRedirect();
        $this->assertSame('9HX7K2LMNP', $order->fresh()->payment_trx_id);

        $this->post('/bn/print-orders/'.$order->id.'/cancel')->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_partner_status_update_flashes_real_message(): void
    {
        // QA minor: the flash was the page title ("আমার প্রিন্ট অর্ডার").
        $prints = app(\App\Services\PrintService::class);
        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData());
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);

        $this->actingAs($this->partner);
        $this->post("/bn/print-partner/orders/{$order->id}/status", ['status' => 'printing'])
            ->assertRedirect()
            ->assertSessionHas('status', 'অর্ডারের অবস্থা আপডেট হয়েছে।');
        $this->assertSame('printing', $order->fresh()->status);
    }

    public function test_partner_cannot_see_another_partners_order(): void
    {
        $order = $this->deliveredOrder();

        $otherPartner = \App\Models\User::factory()->create(['phone' => '01700000015']);
        $otherPartner->assignRole('print-partner');

        $this->actingAs($otherPartner);
        $this->get('/bn/print-partner')->assertOk()->assertDontSee($order->order_no);

        $this->actingAs($this->partner);
        $this->get('/bn/print-partner')->assertOk()->assertSee($order->order_no);
    }

    public function test_livewire_print_order_form_creates_order(): void
    {
        $this->actingAs($this->reader);

        \Livewire\Livewire::test(\App\Livewire\PrintOrderForm::class, ['book' => $this->prophet])
            ->assertSee('৳454')
            ->set('quantity', 2)
            ->assertSee('৳908')
            ->set('delivery_name', 'Test Reader')
            ->set('delivery_phone', '01700000002')
            ->set('delivery_address', '1 Test Road, Dhaka')
            ->call('submit')
            // Livewire tests run with the app default locale (en).
            ->assertRedirect('/en/print-orders/'.PrintOrder::firstOrFail()->id);

        $order = PrintOrder::firstOrFail();
        $this->assertSame(2, $order->quantity);
        $this->assertSame(908, $order->total_bdt);
    }

    public function test_print_pages_render_in_english_too(): void
    {
        $this->actingAs($this->reader);
        $this->get('/en/print-orders')->assertOk()->assertSee('My print orders');
        $this->get('/en/books/the-prophet/print-order')->assertOk()->assertSee('Printed copy order');
    }

    public function test_price_not_set_refusal_flashes_visible_status_message(): void
    {
        // Repro (QA N1): book price null + settings base price empty →
        // create() refuses, but withErrors alone is invisible because
        // layouts/app.blade.php renders only session('status').
        $this->prophet->update(['print_price_bdt' => null]);
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            \Illuminate\Support\Facades\DB::table('settings')
                ->where('key', 'print.base_price_bdt')->delete();
        }

        $this->actingAs($this->reader);
        $this->get('/bn/books/the-prophet/print-order')
            ->assertRedirect()
            ->assertSessionHas('status', 'দাম এখনো নির্ধারণ হয়নি — পরে আবার চেষ্টা করুন।')
            ->assertSessionHasErrors('book');
    }
}
