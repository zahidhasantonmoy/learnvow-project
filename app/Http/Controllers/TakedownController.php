<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\TakedownRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public takedown-claim form (spec §5.3, M1). Anyone may file a claim —
 * no login required. The 48h first-response target is set by the
 * TakedownRequest model on creation.
 */
class TakedownController extends Controller
{
    use BuildsSeo;

    public function create(string $locale): View
    {
        return view('takedown.create', [
            'books' => Book::where('status', 'published')->orderBy('title')->get(['id', 'title']),
            'seo' => $this->seo(__('moderation.takedowns.file_claim'), null, ['noindex' => true]),
        ]);
    }

    public function store(string $locale, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'claimant_name' => ['required', 'string', 'max:255'],
            'claimant_email' => ['required', 'email', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $proofPath = null;

        if ($request->hasFile('proof')) {
            $proofPath = Storage::disk('local')->putFile('takedown-proof', $request->file('proof'));
        }

        TakedownRequest::create([
            'book_id' => $data['book_id'],
            'claimant_name' => $data['claimant_name'],
            'claimant_email' => $data['claimant_email'],
            'reason' => $data['reason'],
            'proof_path' => $proofPath,
        ]);

        return redirect()
            ->route('books.show', ['locale' => $locale, 'slug' => Book::find($data['book_id'])->slug])
            ->with('status', __('moderation.takedowns.claim_submitted'));
    }
}
