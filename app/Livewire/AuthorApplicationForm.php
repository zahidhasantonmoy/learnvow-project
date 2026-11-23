<?php

namespace App\Livewire;

use App\Services\AuthorService;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Become-author application form (§1.1, §11). */
class AuthorApplicationForm extends Component
{
    use WithFileUploads;

    public string $penName = '';
    public string $bio = '';
    public string $bioEn = '';
    public string $bkashNumber = '';
    public $document = null;
    public ?string $error = null;
    public bool $submitted = false;

    public function submit(AuthorService $authors)
    {
        $this->error = null;
        $this->validate([
            'penName' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'bioEn' => 'nullable|string|max:2000',
            'bkashNumber' => 'required|string|max:20',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            // Round 2 minor: the required-document error in the page
            // locale, not Laravel's English default (same pattern as
            // round 1's M6 publish messages).
            // Round 3: same for bKash — novalidate lets Livewire run,
            // so every required field needs its localized message.
            'bkashNumber.required' => __('author.apply_bkash_required'),
            'document.required' => __('author.apply_document_required'),
        ]);

        $user = auth()->user();

        if ($authors->approvedAuthorFor($user) !== null || $authors->activeApplicationFor($user) !== null) {
            $this->error = __('author.apply_already_active');

            return;
        }

        try {
            $authors->submitApplication($user, [
                'pen_name' => $this->penName !== '' ? $this->penName : null,
                'bio' => $this->bio !== '' ? $this->bio : null,
                'bio_en' => $this->bioEn !== '' ? $this->bioEn : null,
                'bkash_number' => $this->bkashNumber,
            ], $this->document);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.author-application-form');
    }
}
