<?php

namespace App\Livewire;

use App\Models\Author;
use App\Services\PayoutService;
use Livewire\Component;

/**
 * Payout request (spec §2.4): the author asks for a payout. The request
 * is a NOTICE to the Super Admin — the monthly payout runs regardless,
 * request or no request.
 */
class PayoutRequest extends Component
{
    public ?int $balance = null;

    public ?string $nextPayout = null;

    public bool $requested = false;

    public bool $notified = false;

    public function mount(PayoutService $payout): void
    {
        $author = $this->author();

        if ($author === null) {
            return;
        }

        $this->balance = $payout->authorBalance($author);

        $payoutDay = $payout->settingInt('pool.payout_day', 10);
        $next = now()->day <= $payoutDay
            ? now()->startOfMonth()->addDays($payoutDay - 1)
            : now()->addMonth()->startOfMonth()->addDays($payoutDay - 1);
        $this->nextPayout = $next->format('Y-m-d');
    }

    public function submit(PayoutService $payout): void
    {
        $author = $this->author();

        if ($author === null) {
            return;
        }

        $this->notified = $payout->recordPayoutRequest($author, auth()->user());
        $this->requested = true;
        $this->balance = $payout->authorBalance($author);
    }

    protected function author(): ?Author
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return Author::where('user_id', $user->id)->first();
    }

    public function render()
    {
        return view('livewire.payout-request');
    }
}
