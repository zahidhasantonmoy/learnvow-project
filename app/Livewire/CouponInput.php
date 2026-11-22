<?php

namespace App\Livewire;

use App\Services\BillingService;
use Livewire\Component;

/** Coupon code check + live discount (server-side, spec §4.3). */
class CouponInput extends Component
{
    public string $code = '';
    public int $salePrice = 0;
    public string $appliesTo = 'books';
    public int $discount = 0;
    public ?string $message = null;

    public function mount(int $salePrice, string $appliesTo = 'books'): void
    {
        $this->salePrice = $salePrice;
        $this->appliesTo = $appliesTo;
    }

    public function check(BillingService $billing): void
    {
        $this->discount = $billing->applyCoupon($this->code, $this->salePrice, $this->appliesTo, auth()->user());
        $this->message = $this->discount > 0 ? __('billing.coupon_applied') : __('billing.coupon_invalid');
    }

    public function render()
    {
        return view('livewire.coupon-input');
    }
}
