<?php

// Builder B — public catalogue + reader routes (spec §3.1, §3.3).
// Loaded inside the {locale} group in routes/web.php, so no locale prefix here.
// Profile routes are deliberately NOT defined here — Builder A owns the
// profile views/controllers; the coordinator wires profile.* after A and B
// finish (see Builder B report).

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// Public (guest) routes.
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/free', [BookController::class, 'free'])->name('books.free');
Route::get('/books/{slug}', [BookController::class, 'show'])->name('books.show');
Route::get('/books/{slug}/sample', [BookController::class, 'sample'])->name('books.sample');
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/authors/{slug}', [AuthorController::class, 'show'])->name('authors.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/refund-policy', [PageController::class, 'refundPolicy'])->name('pages.refund');
Route::get('/copyright', [PageController::class, 'copyright'])->name('pages.copyright');
Route::get('/help', [PageController::class, 'help'])->name('pages.help');

// Logged-in reader routes.
Route::middleware('auth')->group(function () {
    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/books/{slug}/wishlist', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/books/{slug}/review', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/authors/{slug}/follow', [AuthorController::class, 'follow'])->name('authors.follow');
});
