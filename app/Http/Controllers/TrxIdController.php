<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use App\Services\BillingService;
use Illuminate\Http\Request;

class TrxIdController extends Controller
{
    use BuildsSeo;

    public function edit(string $locale, Order $order)
    {
        Gate::authorize('view', $order);
        $payment = $order->payments()->latest()->first();

        return view('checkout.fix-trxid', [
            'order' => $order,
            'payment' => $payment,
            'seo' => $this->seo(__('billing.fix_trxid_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(string $locale, Request $request, Order $order, BillingService $billing)
    {
        Gate::authorize('view', $order);
        $data = $request->validate([
            'method' => 'required|in:bkash,nagad',
            'trx_id' => 'required|string|max:32',
        ]);

        $payment = $order->payments()->latest()->first();

        try {
            if ($payment === null) {
                // First TrxID for this order (order was created without one).
                $billing->submitTrxId($order->fresh(), $data['method'], $data['trx_id']);
            } else {
                Gate::authorize('update', $payment);
                $billing->correctTrxId($payment, $data['trx_id']);
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['trx_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id])
            ->with('status', __('billing.trxid_submitted'));
    }
}
