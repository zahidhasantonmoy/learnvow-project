<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Book;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuthorPrintController extends Controller
{
    use BuildsSeo;

    public function edit(string $locale, Book $book, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);

        return view('author.print-settings', [
            'book' => $book,
            'seo' => $this->seo(__('author.print_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(string $locale, Book $book, Request $request, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);

        $data = $request->validate([
            'allow_pod' => 'nullable|boolean',
            'allow_licensed_pdf' => 'nullable|boolean',
            'print_price_bdt' => 'nullable|integer|min:0',
        ]);

        // Unchecked checkboxes send nothing — treat absence as false.
        $data['allow_pod'] = $request->boolean('allow_pod');
        $data['allow_licensed_pdf'] = $request->boolean('allow_licensed_pdf');

        $authors->updatePrintSettings($authors->approvedAuthorFor($request->user()), $book, $data);

        return back()->with('status', __('author.print_success'));
    }
}
