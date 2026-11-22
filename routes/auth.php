<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes — Builder A (spec §3.2)
|--------------------------------------------------------------------------
| This file is loaded inside the coordinator's {locale} group
| (routes/web.php), so routes are defined WITHOUT the locale prefix.
| Form submissions are handled by the Livewire components mounted in the
| auth views; only logout is a plain POST route.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::get('otp', [AuthController::class, 'showOtp'])->name('otp');
    Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.forgot');
    Route::get('reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout');
