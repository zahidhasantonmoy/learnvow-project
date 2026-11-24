<?php

namespace App\Livewire;

use App\Models\BookRequest;
use Livewire\Component;

/** Book request form (§4.9). Max 5 requests per month (A9). */
class RequestBookForm extends Component
{
    public string $title = '';

    public string $authorName = '';

    public string $reason = '';

    public ?int $sameTitleCount = null;

    public function submit(): void
    {
        $user = auth()->user();

        $thisMonth = BookRequest::where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($thisMonth >= BookRequest::MAX_PER_MONTH) {
            $this->addError('title', __('books.request_monthly_limit', ['max' => BookRequest::MAX_PER_MONTH]));
            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'authorName' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        BookRequest::create([
            'user_id' => $user->id,
            'title' => $this->title,
            'author_name' => $this->authorName ?: null,
            'reason' => $this->reason ?: null,
        ]);

        $this->sameTitleCount = BookRequest::where('title', $this->title)->count();
        $this->reset(['title', 'authorName', 'reason']);
        session()->flash('status', __('books.request_submitted', ['count' => $this->sameTitleCount]));
    }

    public function render()
    {
        return view('livewire.request-book-form', [
            'remaining' => max(0, BookRequest::MAX_PER_MONTH - BookRequest::where('user_id', auth()->id())
                ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count()),
        ]);
    }
}
