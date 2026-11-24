<?php

use App\Http\Controllers\AudioController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BookRequestController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\QuoteCardController;
use App\Http\Controllers\SerialController;
use App\Http\Controllers\TasteProfileController;
use App\Http\Controllers\VocabularyController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\TtsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| LearnVow reader routes — Phase 2 (Builder A)
|--------------------------------------------------------------------------
| Loaded inside the /{locale}/ group in web.php, so every route here is
| already prefixed with {locale} and runs the SetLocale middleware.
| All routes require auth. Spec §8.1 for the full table.
|
| NOTE (Phase 1 convention): Laravel resolves controller arguments
| POSITIONALLY against the route parameters, and {locale} is the
| first route parameter — so every controller method MUST declare
| `string $locale` in its signature (after Request, before the
| model), or the locale string ('bn') lands in the Book argument
| and the binding breaks with a TypeError.
|
| NOTE (signed-file URL): BookFileService::signedFileUrl()
| (coordinator-owned) generates `reader.file.signed` with only
| book+type — no locale. URL generation therefore needs a locale
| default, set here; the signed URL's locale segment is irrelevant
| to the file stream (SetLocale only sets the app locale).
|
| REQUIRED route name (BookFileService::signedFileUrl depends on it):
|   GET /{locale}/reader/{book}/signed-file/{type}
|     -> ReaderController@signedFile, name 'reader.file.signed',
|     signed middleware, re-checks BookService::canRead() then streams
|     via BookFileService (never a direct file link).
*/
URL::defaults(['locale' => 'bn']);

Route::middleware('auth')->group(function () {
    // Reader.
    Route::get('/reader/{book}', [ReaderController::class, 'show'])->name('reader.show');
    Route::get('/reader/{book}/file', [ReaderController::class, 'file'])->name('reader.file');
    Route::get('/reader/{book}/signed-file/{type}', [ReaderController::class, 'signedFile'])
        ->middleware('signed')->name('reader.file.signed');
    Route::get('/reader/{book}/settings', [ReaderController::class, 'settings'])->name('reader.settings');
    Route::put('/reader/{book}/progress', [ReaderController::class, 'progress'])->name('reader.progress');
    Route::get('/reader/{book}/finished', [ReaderController::class, 'finished'])->name('reader.finished');

    // Bookmarks / highlights / notes.
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/reader/{book}/bookmarks', [BookmarkController::class, 'store'])->name('reader.bookmarks.store');
    Route::delete('/reader/{book}/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('reader.bookmarks.destroy');
    Route::post('/reader/{book}/highlights', [HighlightController::class, 'store'])->name('reader.highlights.store');
    Route::delete('/reader/{book}/highlights/{highlight}', [HighlightController::class, 'destroy'])->name('reader.highlights.destroy');
    Route::post('/reader/{book}/notes', [NoteController::class, 'store'])->name('reader.notes.store');
    Route::delete('/reader/{book}/notes/{note}', [NoteController::class, 'destroy'])->name('reader.notes.destroy');

    // Audiobook + TTS.
    Route::get('/audio/{book}', [AudioController::class, 'show'])->name('audio.show');
    Route::get('/audio/{book}/file', [AudioController::class, 'file'])->name('audio.file');
    Route::get('/listen/{book}', [TtsController::class, 'show'])->name('tts.show');

    // Downloads.
    Route::get('/downloads', [DownloadController::class, 'index'])->name('downloads.index');
    Route::post('/reader/{book}/download', [DownloadController::class, 'store'])->name('reader.download');
    Route::delete('/downloads/{download}', [DownloadController::class, 'destroy'])->name('downloads.destroy');

    // Phase 4 — Reader 11 screens (Builder A, spec §14.2).
    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
    Route::post('/collections/{collection}/books/{book}', [CollectionController::class, 'addBook'])->name('collections.books.add');
    Route::delete('/collections/{collection}/books/{book}', [CollectionController::class, 'removeBook'])->name('collections.books.remove');
    Route::get('/for-you', [DiscoveryController::class, 'forYou'])->name('for-you');
    Route::get('/reading-goals', [GoalController::class, 'index'])->name('reading-goals.index');
    Route::post('/reading-goals', [GoalController::class, 'store'])->name('reading-goals.store');
    Route::delete('/reading-goals/{goal}', [GoalController::class, 'destroy'])->name('reading-goals.destroy');
    Route::get('/badges', [BadgeController::class, 'index'])->name('badges.index');
    Route::get('/reader/{book}/quote-card', [QuoteCardController::class, 'show'])->name('reader.quote-card');
    Route::get('/serials/{serial}', [SerialController::class, 'show'])->name('serials.show');
    Route::get('/serials/{serial}/episodes/{episode}', [SerialController::class, 'episode'])->name('serials.episodes.show');
    Route::post('/serials/{serial}/notify', [SerialController::class, 'subscribe'])->name('serials.subscribe');
    Route::get('/preorders', [PreorderController::class, 'index'])->name('preorders.index');
    Route::post('/books/{book}/preorder', [PreorderController::class, 'store'])->name('books.preorder');
    Route::delete('/preorders/{preorder}', [PreorderController::class, 'destroy'])->name('preorders.destroy');
    Route::get('/request-book', [BookRequestController::class, 'create'])->name('books.request.create');
    Route::post('/request-book', [BookRequestController::class, 'store'])->name('books.request.store');
    Route::get('/profile/taste', [TasteProfileController::class, 'edit'])->name('profile.taste.edit');
    Route::put('/profile/taste', [TasteProfileController::class, 'update'])->name('profile.taste.update');
    Route::get('/vocabulary', [VocabularyController::class, 'index'])->name('vocabulary.index');
    Route::post('/vocabulary', [VocabularyController::class, 'store'])->name('vocabulary.store');
    Route::delete('/vocabulary/{word}', [VocabularyController::class, 'destroy'])->name('vocabulary.destroy');
});
