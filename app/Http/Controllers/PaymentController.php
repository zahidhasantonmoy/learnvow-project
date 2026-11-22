<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use App\Services\BillingService;

class PaymentController extends Controller
{
    use BuildsSeo;

    /** Payment hub (v3.21 screen 136): wallet, TrxID status, subscription, orders. */
    public function index(string $locale, BillingService $billing)
    {
        $user = auth()->user();

        return view('payment.index', [
            'user' => $user,
            'subscription' => $billing->currentSubscription($user),
            'orders' => Order::where('user_id', $user->id)->latest()->limit(5)->get(),
            'payments' => \App\Models\Payment::where('user_id', $user->id)->latest()->limit(5)->get(),
            'referralCredit' => $billing->referralCreditTotal($user),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
            'seo' => $this->seo(__('billing.payment_title'), null, ['noindex' => true]),
        ]);
    }

    public function success(string $locale, Order $order)
    {
        Gate::authorize('view', $order);

        return view('payment.success', [
            'order' => $order->load('items', 'payments'),
            'seo' => $this->seo(__('billing.payment_success'), null, ['noindex' => true]),
        ]);
    }

    public function failed(string $locale, Order $order)
    {
        Gate::authorize('view', $order);

        return view('payment.failed', [
            'order' => $order->load('items', 'payments'),
            'seo' => $this->seo(__('billing.payment_failed'), null, ['noindex' => true]),
        ]);
    }
}
