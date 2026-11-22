<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TrxIdController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LearnVow billing routes — Phase 2 (Builder B)
|--------------------------------------------------------------------------
| Loaded inside the /{locale}/ group in web.php, so every route here is
| already prefixed with {locale} and runs the SetLocale middleware.
| All routes require auth (except gift/redeem GET, which a guest may
| view and claims after logging in). Spec §8.2 for the full table.
*/

// Gift redeem: guests may view; claiming needs login.
Route::get('/gift/redeem', [GiftController::class, 'redeem'])->name('gift.redeem');

Route::middleware('auth')->group(function () {
    Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

    // /checkout/premium must come before /checkout/{book}.
    Route::get('/checkout/premium', [CheckoutController::class, 'premium'])->name('checkout.premium');
    Route::get('/checkout/{book}', [CheckoutController::class, 'book'])->name('checkout.book');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
    Route::get('/payment/success/{order}', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/payment/failed/{order}', [PaymentController::class, 'failed'])->name('payment.failed');
    Route::get('/payment/fix-trxid/{order}', [TrxIdController::class, 'edit'])->name('payment.fix-trxid.edit');
    Route::post('/payment/fix-trxid/{order}', [TrxIdController::class, 'update'])->name('payment.fix-trxid.update');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/topup', [WalletController::class, 'topup'])->name('wallet.topup');
    Route::get('/wallet/saved', [WalletController::class, 'saved'])->name('wallet.saved');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

    Route::get('/points', [PointController::class, 'index'])->name('points.index');
    Route::get('/referral', [ReferralController::class, 'index'])->name('referral.index');

    Route::get('/gift/buy/{book}', [GiftController::class, 'buy'])->name('gift.buy');
    Route::post('/gift/redeem', [GiftController::class, 'claim'])->name('gift.claim');
});
