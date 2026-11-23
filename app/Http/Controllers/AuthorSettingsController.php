<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AuthorSettingsController extends Controller
{
    use BuildsSeo;

    public function edit(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        return view('author.settings', [
            'author' => $author,
            'maskedBkash' => AuthorService::maskedNumber($author->bkash_number),
            'seo' => $this->seo(__('author.settings_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(string $locale, Request $request, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor($request->user());
        abort_unless($author !== null, 403);

        $data = $request->validate([
            'pen_name' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'bio_en' => 'nullable|string|max:2000',
            'bkash_number' => 'nullable|string|max:20',
            'payout_method' => 'nullable|in:bkash,bank',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
        ]);

        try {
            $authors->updateAuthorSettings($author, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bkash_number' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('author.settings_success'));
    }
}
