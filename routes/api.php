<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HighlightController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PremiumController;
use App\Http\Controllers\Api\PrintOrderController;
use App\Http\Controllers\Api\ReaderController;
use App\Http\Controllers\Api\VocabularyController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LearnVow Sanctum API — Phase 4 (Builder B owns this file).
| Spec: docs/laravel-phase4-build-spec.md §1.3, §14.1.
| All routes under /api/v1/, throttle 60/min; auth:sanctum except
| login/register. Login has a loose per-IP route cap (30/min,
| api-login-ip) plus a strict per-phone+IP 5/min limit inside
| AuthController::login (SEC-02), so it does not share the group
| 60/min counter — without the prefix each attempt consumed 2 hits
| against the 5-limit (QA N2).
|--------------------------------------------------------------------------
*/

// Locale for API comes from Accept-Language (bn|en, default bn) —
// no locale prefix in the URL (spec §1.3).
Route::prefix('v1')->middleware(['throttle:60,1', SetApiLocale::class])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1,api-register');
    // SEC-02: the per-IP route cap is deliberately loose (30/min); the
    // strict 5/min limit is per phone+IP inside AuthController::login
    // (mirrors the web LoginForm), so one user's failures never lock
    // out everyone behind a shared IP.
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:30,1,api-login-ip');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/books', [BookController::class, 'index']);
        Route::get('/books/{book}', [BookController::class, 'show']);
        Route::get('/search', [BookController::class, 'search']);
        Route::get('/library', [LibraryController::class, 'index']);

        Route::get('/reader/{book}/progress', [ReaderController::class, 'progress']);
        Route::put('/reader/{book}/progress', [ReaderController::class, 'saveProgress']);
        Route::get('/reader/{book}/file', [ReaderController::class, 'file']);
        Route::post('/reader/{book}/download', [DownloadController::class, 'store']);
        Route::get('/reader/{book}/download/{download}', [DownloadController::class, 'file'])
            ->name('api.v1.reader.download.file');
        Route::get('/reader/{book}/download-key', [DownloadController::class, 'downloadKey'])
            ->name('api.v1.reader.download-key');

        Route::get('/reader/{book}/bookmarks', [BookmarkController::class, 'index']);
        Route::post('/reader/{book}/bookmarks', [BookmarkController::class, 'store']);
        Route::delete('/reader/{book}/bookmarks/{bookmark}', [BookmarkController::class, 'destroy']);
        Route::get('/reader/{book}/highlights', [HighlightController::class, 'index']);
        Route::post('/reader/{book}/highlights', [HighlightController::class, 'store']);
        Route::delete('/reader/{book}/highlights/{highlight}', [HighlightController::class, 'destroy']);
        Route::get('/reader/{book}/notes', [NoteController::class, 'index']);
        Route::post('/reader/{book}/notes', [NoteController::class, 'store']);
        Route::delete('/reader/{book}/notes/{note}', [NoteController::class, 'destroy']);

        Route::get('/premium', [PremiumController::class, 'show']);
        Route::post('/premium/trial', [PremiumController::class, 'trial']);

        Route::post('/orders', [OrderController::class, 'store']);
        Route::post('/orders/{order}/trxid', [OrderController::class, 'submitTrxId']);
        Route::get('/wallet', [WalletController::class, 'show']);

        Route::get('/collections', [CollectionController::class, 'index']);
        Route::post('/collections', [CollectionController::class, 'store']);
        Route::get('/for-you', [DiscoveryController::class, 'forYou']);
        Route::get('/goals', [GoalController::class, 'index']);
        Route::post('/goals', [GoalController::class, 'store']);
        Route::get('/badges', [BadgeController::class, 'index']);
        Route::get('/vocabulary', [VocabularyController::class, 'index']);
        Route::post('/vocabulary', [VocabularyController::class, 'store']);

        Route::get('/devices', [DeviceController::class, 'index']);
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

        Route::post('/fcm-token', [FcmTokenController::class, 'store']);
        Route::delete('/fcm-token', [FcmTokenController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/print-orders', [PrintOrderController::class, 'index']);
        Route::post('/books/{book}/print-order', [PrintOrderController::class, 'store']);
    });
});
