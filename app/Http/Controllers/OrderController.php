<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use BuildsSeo;

    public function index()
    {
        $orders = Order::where('user_id', auth()->id())->with('items')->latest()->paginate(20);

        return view('orders.index', [
            'orders' => $orders,
            'seo' => $this->seo(__('billing.my_orders'), null, ['noindex' => true]),
        ]);
    }

    public function show(string $locale, Order $order)
    {
        Gate::authorize('view', $order);

        return view('orders.show', [
            'order' => $order->load('items', 'payments.events', 'coupon'),
            'seo' => $this->seo(__('billing.order_detail'), null, ['noindex' => true]),
        ]);
    }

    /** Refund request (spec §3.6): under review; approval is Super Admin only. */
    public function refund(string $locale, Request $request, Order $order)
    {
        Gate::authorize('view', $order);
        if ($order->status !== 'paid') {
            return back()->with('status', __('billing.refund_not_available'));
        }
        if ($order->refund_status !== 'none') {
            return back()->with('status', __('billing.refund_already_requested'));
        }

        $order->update([
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
        ]);

        return back()->with('status', __('billing.refund_requested'));
    }
}
