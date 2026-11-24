<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Http\Request;

/** Orders + manual TrxID (spec §1.3). Prices recomputed server-side — never trusted from the client. */
class OrderController extends ApiController
{
    public function store(Request $request, BillingService $billing)
    {
        $data = $request->validate([
            'type' => 'required|in:book,premium,gift,wallet_topup',
            'book_id' => 'nullable|exists:books,id',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'coupon_code' => 'nullable|string|max:32',
            'wallet_use_bdt' => 'nullable|integer|min:0',
            'topup_amount_bdt' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();

        if (in_array($data['type'], ['book', 'gift'], true)) {
            if (empty($data['book_id'])) {
                return $this->fail(__('api.book_required'), 422);
            }
            $book = \App\Models\Book::findOrFail($data['book_id']);
            $authorId = \DB::table('authors')->where('user_id', $user->id)->value('id');
            if ($authorId !== null && (int) $book->author_id === (int) $authorId) {
                return $this->fail(__('api.author_cannot_buy_own'), 422);
            }
        }
        if ($data['type'] === 'premium' && $billing->hasActivePremium($user)) {
            return $this->fail(__('api.already_premium'), 422);
        }

        $data['user'] = $user;

        try {
            $priced = $billing->priceOrder($data);
            $order = $billing->createOrder($user, $priced);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->orderArray($order), 201);
    }

    public function submitTrxId(Request $request, Order $order, BillingService $billing)
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'method' => 'required|in:bkash,nagad',
            'trx_id' => 'required|string|max:32',
        ]);

        try {
            $payment = $billing->submitTrxId($order, $data['method'], $data['trx_id']);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'order' => $this->orderArray($order->fresh()),
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
        ]);
    }

    private function orderArray(Order $order): array
    {
        return [
            'id' => $order->id, 'order_no' => $order->order_no, 'type' => $order->type,
            'status' => $order->status, 'subtotal_bdt' => (int) $order->subtotal_bdt,
            'coupon_amt_bdt' => (int) $order->coupon_amt_bdt,
            'wallet_used_bdt' => (int) $order->wallet_used_bdt,
            'total_bdt' => (int) $order->total_bdt,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
