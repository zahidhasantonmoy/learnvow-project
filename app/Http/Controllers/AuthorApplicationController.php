<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\AuthorApplication;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AuthorApplicationController extends Controller
{
    use BuildsSeo;

    /** Apply form (Reader). Approved authors go straight to the Studio. */
    public function create(string $locale, AuthorService $authors)
    {
        $user = auth()->user();

        if ($authors->approvedAuthorFor($user) !== null) {
            return redirect()->route('author.dashboard', ['locale' => $locale]);
        }

        $application = $authors->activeApplicationFor($user);

        return view('author.apply', [
            'application' => $application,
            'seo' => $this->seo(__('author.apply_title'), null, ['noindex' => true]),
        ]);
    }

    /** Non-Livewire fallback submit (the form is Livewire AuthorApplicationForm). */
    public function store(string $locale, Request $request, AuthorService $authors)
    {
        $data = $request->validate([
            'pen_name' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'bio_en' => 'nullable|string|max:2000',
            'bkash_number' => 'required|string|max:20',
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            // Round 2 minor: required-document error in the page locale
            // (same key as the Livewire form's `document` field).
            'id_document.required' => __('author.apply_document_required'),
        ]);

        try {
            $authors->submitApplication(
                $request->user(),
                $data,
                $request->file('id_document')
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bkash_number' => $e->getMessage()])->withInput();
        }

        return redirect()->route('author.apply', ['locale' => $locale])
            ->with('status', __('author.apply_success'));
    }

    /** Admin review hook (Filament calls the service; kept for parity). */
    public function review(string $locale, AuthorApplication $application, Request $request, AuthorService $authors)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'super-admin']), 403);

        $data = $request->validate([
            'approve' => 'required|boolean',
            'note' => 'nullable|string|max:2000',
        ]);

        $authors->reviewApplication($application, $request->user(), (bool) $data['approve'], $data['note'] ?? null);

        return back()->with('status', __('author.apply_success'));
    }
}
