<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Coordinator-wired profile routes (views by Builder A; B deliberately
// left these out). All inside the {locale} group via web.php.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/privacy', [ProfileController::class, 'privacy'])->name('profile.privacy');
    Route::get('/profile/delete', [ProfileController::class, 'delete'])->name('profile.delete');
    Route::post('/profile/delete', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
