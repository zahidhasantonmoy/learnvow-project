<?php

// LearnVow Author Studio routes — Phase 3 (Builder A owns this file).
// All routes live under /{locale}/ (bn|en), auth middleware, noindex.
// Spec: docs/laravel-phase3-build-spec.md §9.1.
//
// NOTE: the earnings / pool / analytics / payout-request routes point at
// Builder B's controllers and the support/chat routes at Builder C's
// SupportChatController — those classes do not exist yet. Registering
// them is safe (Laravel resolves the controller only when the route is
// hit); the routes 404/500 until B/C land their controllers.

use App\Http\Controllers\AuthorApplicationController;
use App\Http\Controllers\AuthorAudioController;
use App\Http\Controllers\AuthorBookController;
use App\Http\Controllers\AuthorDashboardController;
use App\Http\Controllers\AuthorEarningController;
use App\Http\Controllers\AuthorAnalyticsController;
use App\Http\Controllers\AuthorPoolController;
use App\Http\Controllers\AuthorPrintController;
use App\Http\Controllers\AuthorPrintCopiesController;
use App\Http\Controllers\AuthorPromoController;
use App\Http\Controllers\AuthorReviewController;
use App\Http\Controllers\AuthorSerialController;
use App\Http\Controllers\AuthorSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\TaxKycController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Author application (§1.1).
    Route::get('/author/apply', [AuthorApplicationController::class, 'create'])->name('author.apply');
    Route::post('/author/apply', [AuthorApplicationController::class, 'store'])->name('author.apply.store');

    // Author Studio dashboard (§1.2).
    Route::get('/author', [AuthorDashboardController::class, 'index'])->name('author.dashboard');

    // Publish (§1.3).
    Route::get('/author/publish', [AuthorBookController::class, 'create'])->name('author.publish');
    Route::post('/author/publish', [AuthorBookController::class, 'store'])->name('author.publish.store');

    // My books (§1.4).
    Route::get('/author/books', [AuthorBookController::class, 'index'])->name('author.books');
    Route::get('/author/books/{book}/edit', [AuthorBookController::class, 'edit'])->name('author.books.edit');
    Route::put('/author/books/{book}', [AuthorBookController::class, 'update'])->name('author.books.update');
    Route::post('/author/books/{book}/pause', [AuthorBookController::class, 'pause'])->name('author.books.pause');
    Route::post('/author/books/{book}/resume', [AuthorBookController::class, 'resume'])->name('author.books.resume');
    Route::post('/author/books/{book}/appeal', [AuthorBookController::class, 'appeal'])->name('author.books.appeal');

    // Serials (§4.7) — author creates, adds episodes via
    // SerialEpisodeManager; Admin approves (AdminService::approveSerial).
    Route::get('/author/serials', [AuthorSerialController::class, 'index'])->name('author.serials.index');
    Route::get('/author/serials/create', [AuthorSerialController::class, 'create'])->name('author.serials.create');
    Route::post('/author/serials', [AuthorSerialController::class, 'store'])->name('author.serials.store');
    Route::get('/author/serials/{serial}', [AuthorSerialController::class, 'show'])->name('author.serials.show');

    // Audiobook (§1.5).
    Route::get('/author/books/{book}/audio', [AuthorAudioController::class, 'create'])->name('author.books.audio');
    Route::post('/author/books/{book}/audio', [AuthorAudioController::class, 'store'])->name('author.books.audio.store');

    // Earnings / pool / analytics — Builder B's controllers (§9.1).
    Route::get('/author/earnings', [AuthorEarningController::class, 'index'])->name('author.earnings');
    Route::get('/author/pool', [AuthorPoolController::class, 'index'])->name('author.pool');
    Route::get('/author/analytics', [AuthorAnalyticsController::class, 'index'])->name('author.analytics');
    Route::post('/author/payouts/request', [AuthorEarningController::class, 'requestPayout'])->name('author.payouts.request');

    // Reviews & replies (§1.8).
    Route::get('/author/reviews', [AuthorReviewController::class, 'index'])->name('author.reviews');
    Route::post('/author/reviews/{review}/reply', [AuthorReviewController::class, 'reply'])->name('author.reviews.reply');

    // Promos (§1.9).
    Route::get('/author/promos', [AuthorPromoController::class, 'index'])->name('author.promos');
    Route::post('/author/promos', [AuthorPromoController::class, 'store'])->name('author.promos.store');

    // Print settings (§1.6).
    Route::get('/author/print-settings/{book}', [AuthorPrintController::class, 'edit'])->name('author.print-settings.edit');
    Route::put('/author/print-settings/{book}', [AuthorPrintController::class, 'update'])->name('author.print-settings.update');

    // Author's own printed copies (Phase 4 §2.5 — Builder C).
    Route::get('/author/print-copies', [AuthorPrintCopiesController::class, 'index'])->name('author.print-copies.index');
    Route::post('/author/print-copies', [AuthorPrintCopiesController::class, 'store'])->name('author.print-copies.store');

    // Tax & KYC (Phase 4 §3.3 — Builder C).
    Route::get('/author/tax-kyc', [TaxKycController::class, 'edit'])->name('author.tax-kyc.edit');
    Route::put('/author/tax-kyc', [TaxKycController::class, 'update'])->name('author.tax-kyc.update');

    // Payout settings & profile (§1.7).
    Route::get('/author/settings', [AuthorSettingsController::class, 'edit'])->name('author.settings');
    Route::put('/author/settings', [AuthorSettingsController::class, 'update'])->name('author.settings.update');

    // Support chat — Builder C's controller (§3.5, §9.1).
    Route::get('/support/chat', [SupportChatController::class, 'index'])->name('support.chat');
    Route::post('/support/chat', [SupportChatController::class, 'store'])->name('support.chat.store');
    Route::get('/support/chat/{ticket}', [SupportChatController::class, 'show'])->name('support.chat.show');
    Route::post('/support/chat/{ticket}/reply', [SupportChatController::class, 'reply'])->name('support.chat.reply');

    // Notifications (§6).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Notification settings (profile/notifications.blade.php — no §9.1 row;
    // the view needs a route, so it lives here with the other author routes).
    Route::get('/profile/notifications', [NotificationController::class, 'settings'])->name('profile.notifications');
    Route::put('/profile/notifications', [NotificationController::class, 'updateSettings'])->name('profile.notifications.update');
});
