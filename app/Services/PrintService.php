<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\PrintOrder;
use App\Models\User;
use App\Notifications\AuthorNotification;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Manual print / POD orders (Phase 4 spec §2).
 *
 * All money is int (৳) and recomputed server-side — the client's
 * amounts are never trusted. Price source order (§2.3): the book's
 * print_price_bdt, else Super Admin's print.base_price_bdt; if neither
 * is set the order cannot be placed. Partner cost is undecided
 * (spec A4) — Admin writes it per order when sending to the partner.
 *
 * Payment: manual TrxID (spec §2.1). See the print_orders migration
 * note — a `payments` row cannot exist for a print order in Phase 2's
 * schema (payments.order_id is NOT NULL), so the buyer's TrxID is
 * stored on the print order until the coordinator decides otherwise.
 * Admin verifies the TrxID by hand (bKash/Nagad app) and confirms.
 */
class PrintService
{
    public const MAX_QUANTITY = 10;

    /** Read a settings key. Null when the table/key is absent. */
    public function setting(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public function settingIntOrNull(string $key): ?int
    {
        $value = $this->setting($key);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Price an order server-side (§2.3). Returns unit / delivery / total
     * plus where the unit price came from. Throws when no price exists.
     */
    public function priceOrder(Book $book, int $quantity = 1): array
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new InvalidArgumentException('Quantity must be between 1 and '.self::MAX_QUANTITY.'.');
        }

        $unit = $book->print_price_bdt !== null
            ? (int) $book->print_price_bdt
            : $this->settingIntOrNull('print.base_price_bdt');

        if ($unit === null) {
            throw new InvalidArgumentException('Print price has not been set yet.');
        }

        $delivery = $this->settingIntOrNull('print.delivery_charge_bdt') ?? 0;

        return [
            'unit_price_bdt' => $unit,
            'delivery_charge_bdt' => $delivery,
            'total_bdt' => $quantity * $unit + $delivery,
            'quantity' => $quantity,
            'price_source' => $book->print_price_bdt !== null ? 'book' : 'settings',
        ];
    }

    /** Next sequential order number: LV-P-2026-0001. */
    public function nextOrderNo(): string
    {
        $year = now()->format('Y');
        $seq = PrintOrder::where('order_no', 'like', "LV-P-{$year}-%")->count() + 1;

        return sprintf('LV-P-%s-%04d', $year, $seq);
    }

    /**
     * Create a print order (§2.1/§2.2). $data: quantity, delivery_name,
     * delivery_phone, delivery_address, district?. $orderedByAuthor for
     * the author's own copies (§2.5) — those never accrue author share.
     * This is also the exact method Builder B's Api\PrintOrderController
     * calls (§2.2).
     */
    public function createOrder(User $user, Book $book, array $data, bool $orderedByAuthor = false): PrintOrder
    {
        if (! $book->allow_pod) {
            throw new InvalidArgumentException('Print copies are not available for this book.');
        }

        if ($book->status !== 'published' || $book->is_paused) {
            throw new InvalidArgumentException('Print copies are only available for published books.');
        }

        if ($book->author_id === null) {
            throw new InvalidArgumentException('This book has no author.');
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $priced = $this->priceOrder($book, $quantity);

        $name = trim((string) ($data['delivery_name'] ?? ''));
        $phone = trim((string) ($data['delivery_phone'] ?? ''));
        $address = trim((string) ($data['delivery_address'] ?? ''));

        if ($name === '' || $phone === '' || $address === '') {
            throw new InvalidArgumentException('Delivery name, phone and address are required.');
        }

        return DB::transaction(function () use ($user, $book, $data, $orderedByAuthor, $priced, $name, $phone, $address) {
            $order = PrintOrder::create([
                'order_no' => $this->nextOrderNo(),
                'user_id' => $user->id,
                'book_id' => $book->id,
                'author_id' => $book->author_id,
                'quantity' => $priced['quantity'],
                'unit_price_bdt' => $priced['unit_price_bdt'],
                'delivery_charge_bdt' => $priced['delivery_charge_bdt'],
                'total_bdt' => $priced['total_bdt'],
                'status' => PrintOrder::STATUS_ORDERED,
                'delivery_name' => $name,
                'delivery_phone' => $phone,
                'delivery_address' => $address,
                'district' => $data['district'] ?? null,
                'ordered_by_author' => $orderedByAuthor,
            ]);

            $this->audit($user, 'print_order.created', $order, [
                'order_no' => $order->order_no, 'total_bdt' => $order->total_bdt,
            ]);

            return $order->fresh();
        });
    }

    /**
     * Buyer submits the manual TrxID after Send Money (§2.1).
     * Stored on the print order (see class note — no `payments` row can
     * exist for a print order yet). The order stays `ordered` until an
     * Admin verifies the TrxID and confirms it.
     */
    public function submitTrxId(PrintOrder $order, string $method, string $trxId): PrintOrder
    {
        if ($order->status !== PrintOrder::STATUS_ORDERED) {
            throw new InvalidArgumentException('TrxID can only be submitted for an ordered print order.');
        }

        if (! in_array($method, ['bkash', 'nagad'], true)) {
            throw new InvalidArgumentException('Payment method must be bKash or Nagad.');
        }

        if (! BillingService::isValidTrxId($trxId)) {
            throw new InvalidArgumentException('TrxID format is invalid.');
        }

        $order->update([
            'payment_method' => $method,
            'payment_trx_id' => BillingService::normalizeTrxId($trxId),
        ]);

        $this->audit($order->user, 'print_order.trxid_submitted', $order, ['method' => $method]);

        return $order->fresh();
    }

    /**
     * Admin verified the TrxID (by hand, in bKash/Nagad): ordered →
     * confirmed. The order may now be sent to the partner — never before.
     */
    public function onPaymentVerified(PrintOrder $order, User $admin): PrintOrder
    {
        if ($order->status === PrintOrder::STATUS_CONFIRMED) {
            return $order;
        }

        if ($order->status !== PrintOrder::STATUS_ORDERED) {
            throw new InvalidArgumentException('Only an ordered print order can be confirmed.');
        }

        return $this->changeStatus($order, PrintOrder::STATUS_CONFIRMED, $admin);
    }

    /**
     * Admin sends a confirmed order to a print partner (§2.2).
     * Partner cost is written here (spec A4 — undecided, nullable).
     */
    public function sendToPartner(PrintOrder $order, User $partner, ?int $partnerCost, User $admin): PrintOrder
    {
        if ($order->status !== PrintOrder::STATUS_CONFIRMED) {
            throw new InvalidArgumentException('Only a confirmed print order can be sent to a partner.');
        }

        if (! $partner->hasRole('print-partner')) {
            throw new InvalidArgumentException('The selected user is not a print partner.');
        }

        if ($partnerCost !== null && $partnerCost < 0) {
            throw new InvalidArgumentException('Partner cost cannot be negative.');
        }

        $order->update([
            'partner_id' => $partner->id,
            'partner_cost_bdt' => $partnerCost,
        ]);

        return $this->changeStatus($order, PrintOrder::STATUS_SENT_TO_PARTNER, $admin);
    }

    /** Partner: accept the order and start printing. */
    public function markPrinting(PrintOrder $order, User $partner): PrintOrder
    {
        $this->assertPartner($order, $partner);

        if ($order->status !== PrintOrder::STATUS_SENT_TO_PARTNER) {
            throw new InvalidArgumentException('Only an order sent to the partner can move to printing.');
        }

        return $this->changeStatus($order, PrintOrder::STATUS_PRINTING, $partner);
    }

    /** Partner: ship the printed order, with a tracking number. */
    public function markShipped(PrintOrder $order, User $partner, ?string $trackingNo = null): PrintOrder
    {
        $this->assertPartner($order, $partner);

        if ($order->status !== PrintOrder::STATUS_PRINTING) {
            throw new InvalidArgumentException('Only a printing order can be shipped.');
        }

        if ($trackingNo !== null && trim($trackingNo) !== '') {
            $order->update(['tracking_no' => trim($trackingNo)]);
        }

        return $this->changeStatus($order, PrintOrder::STATUS_SHIPPED, $partner);
    }

    /**
     * Admin confirms delivery. This is the ONLY place author share
     * accrues for print (§2.3) — via PayoutService::accruePrintSale().
     */
    public function markDelivered(PrintOrder $order, User $admin): PrintOrder
    {
        if ($order->status !== PrintOrder::STATUS_SHIPPED) {
            throw new InvalidArgumentException('Only a shipped print order can be delivered.');
        }

        $order = $this->changeStatus($order, PrintOrder::STATUS_DELIVERED, $admin);

        app(PayoutService::class)->accruePrintSale($order->fresh());

        return $order->fresh();
    }

    /**
     * Cancel rules (§2.1): the buyer may cancel only before confirmation;
     * after sent_to_partner only an Admin may cancel (having told the
     * partner). Delivered orders can never be cancelled.
     */
    public function cancel(PrintOrder $order, User $user): PrintOrder
    {
        if ($order->isTerminal()) {
            throw new InvalidArgumentException('This print order can no longer be cancelled.');
        }

        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        if ($order->user_id === $user->id && ! $isAdmin) {
            if (! $order->isCancellableByBuyer()) {
                throw new InvalidArgumentException('A print order can only be cancelled before it is confirmed.');
            }
        } elseif (! $isAdmin) {
            throw new InvalidArgumentException('Only the buyer or an Admin can cancel a print order.');
        }

        return $this->changeStatus($order, PrintOrder::STATUS_CANCELLED, $user);
    }

    /** Partner writes a note for the Admin on their own order (§2.2). */
    public function partnerNote(PrintOrder $order, User $partner, string $note): PrintOrder
    {
        $this->assertPartner($order, $partner);

        $order->update(['admin_note' => trim($note)]);
        $this->audit($partner, 'print_order.partner_note', $order, []);

        return $order->fresh();
    }

    protected function assertPartner(PrintOrder $order, User $partner): void
    {
        if ((int) $order->partner_id !== (int) $partner->id) {
            throw new InvalidArgumentException('This order was not assigned to this partner.');
        }
    }

    protected function changeStatus(PrintOrder $order, string $status, ?User $actor): PrintOrder
    {
        $from = $order->status;
        $order->update(['status' => $status]);
        $order = $order->fresh();

        $this->audit($actor, 'print_order.status', $order, ['from' => $from, 'to' => $status]);

        if ($order->user !== null) {
            $order->user->notify(new AuthorNotification('print_order_status', [
                'order_no' => $order->order_no, 'status' => $status,
            ]));
        }

        return $order;
    }

    protected function audit(?User $actor, string $action, PrintOrder $order, array $details): void
    {
        AdminService::writeAudit($actor, $action, 'print_orders', $order->id, $details);
    }
}
