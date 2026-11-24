<?php

// LearnVow print routes — Phase 4 (Builder C owns this file).
// Required inside the /{locale}/ group in routes/web.php.
// Spec: docs/laravel-phase4-build-spec.md §14.4.

use App\Http\Controllers\LicensedPdfController;
use App\Http\Controllers\PrintOrderController;
use App\Http\Controllers\PrintPartnerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Print order form + submit (§2.1).
    Route::get('/books/{book}/print-order', [PrintOrderController::class, 'create'])->name('print-orders.create');
    Route::post('/books/{book}/print-order', [PrintOrderController::class, 'store'])->name('print-orders.store');

    // My print orders (§2.1).
    Route::get('/print-orders', [PrintOrderController::class, 'index'])->name('print-orders.index');
    Route::get('/print-orders/{printOrder}', [PrintOrderController::class, 'show'])->name('print-orders.show');
    Route::post('/print-orders/{printOrder}/trxid', [PrintOrderController::class, 'submitTrxId'])->name('print-orders.trxid');
    Route::post('/print-orders/{printOrder}/cancel', [PrintOrderController::class, 'cancel'])->name('print-orders.cancel');

    // Licensed print-ready PDF (§2.4).
    Route::get('/books/{book}/licensed-pdf', [LicensedPdfController::class, 'download'])->name('books.licensed-pdf');

    // Print Partner dashboard (§2.2).
    Route::get('/print-partner', [PrintPartnerController::class, 'index'])->name('print-partner.index');
    Route::post('/print-partner/orders/{printOrder}/status', [PrintPartnerController::class, 'updateStatus'])->name('print-partner.orders.status');
});
