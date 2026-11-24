<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\TaxKycProfile;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Author Tax & KYC form (Phase 4 spec §3.3, §14.4). */
class TaxKycController extends Controller
{
    use BuildsSeo;

    public function edit(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        $profile = TaxKycProfile::firstOrNew(['author_id' => $author->id]);

        return view('author.tax-kyc', [
            'author' => $author,
            'profile' => $profile,
            'seo' => $this->seo(__('author.tax_kyc_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(string $locale, Request $request, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor($request->user());
        abort_unless($author !== null, 403);

        $data = $request->validate([
            'legal_name' => 'required|string|max:255',
            // Sensitive numbers are never rendered back to the page
            // (spec §3.3): a blank field keeps the stored value.
            'nid_no' => 'nullable|string|max:64',
            'tin_no' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:1000',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:64',
        ]);

        $profile = TaxKycProfile::firstOrNew(['author_id' => $author->id]);

        if (trim((string) ($data['nid_no'] ?? '')) === '' && $profile->nid_no === null) {
            return back()->withErrors(['nid_no' => __('author.tax_kyc_nid')])->withInput();
        }

        // Blank sensitive fields keep their stored (encrypted) values.
        foreach (['nid_no', 'tin_no', 'bank_account_number'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                unset($data[$field]);
            }
        }

        $profile->fill($data);
        // Resubmission after a rejection goes back to pending.
        $profile->status = TaxKycProfile::STATUS_PENDING;
        $profile->reviewed_by = null;
        $profile->review_note = null;
        $profile->save();

        return back()->with('status', __('author.tax_kyc_saved'));
    }
}
