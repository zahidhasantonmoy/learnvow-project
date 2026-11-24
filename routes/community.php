<?php

// LearnVow community routes — Phase 4 (Builder C owns this file).
// Required inside the /{locale}/ group in routes/web.php.
// Spec: docs/laravel-phase4-build-spec.md §14.5.

use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubPostController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Community feed + find friends (§6.1, §6.3).
    Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
    Route::get('/find-friends', [CommunityController::class, 'findFriends'])->name('community.find-friends');

    // Clubs (§6.2).
    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::post('/clubs', [ClubController::class, 'store'])->name('clubs.store');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');
    Route::post('/clubs/{club}/join', [ClubController::class, 'join'])->name('clubs.join');
    Route::post('/clubs/{club}/leave', [ClubController::class, 'leave'])->name('clubs.leave');

    // Club discussion posts (§6.2).
    Route::post('/clubs/{club}/posts', [ClubPostController::class, 'store'])->name('clubs.posts.store');
    Route::delete('/clubs/{club}/posts/{post}', [ClubPostController::class, 'destroy'])->name('clubs.posts.destroy');

    // Report a club post (§6.2) — reports table, type community_posts.
    Route::post('/clubs/{club}/posts/{post}/report', [ReportController::class, 'storePost'])->name('reports.post');
});
