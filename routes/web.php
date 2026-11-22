<?php

use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LearnVow web routes — Phase 1
|--------------------------------------------------------------------------
| All public routes live under /{locale}/ (bn|en). The locale group loads
| one route file per builder (ownership, spec §6): books.php (Builder B),
| auth.php (Builder A), admin.php (Builder C, top-level only).
| routes/api.php is untouched in Phase 1 (Sanctum API is Phase 4).
*/

Route::get('/', function () {
    $locale = request()->cookie('lv_locale');

    if (! in_array($locale, ['bn', 'en'], true)) {
        $accept = strtolower(request()->header('Accept-Language', ''));
        $locale = str_starts_with($accept, 'bn') ? 'bn' : 'en';
    }

    return redirect('/'.$locale);
});

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/robots.txt', [RobotsController::class, 'index']);

Route::prefix('{locale}')->where(['locale' => 'bn|en'])->middleware(SetLocale::class)->group(function () {
    require __DIR__.'/books.php';
    require __DIR__.'/auth.php';
    require __DIR__.'/profile.php';
});

// Admin routes outside the locale prefix (Filament serves /admin itself).
require __DIR__.'/admin.php';
