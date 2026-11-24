<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\PrintService;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Print order form (Phase 4 spec §2.1, §16). Shows the server-priced
 * total live as the quantity changes; submits through PrintService.
 */
class PrintOrderForm extends Component
{
    public Book $book;

    public int $quantity = 1;

    public string $delivery_name = '';

    public string $delivery_phone = '';

    public string $delivery_address = '';

    public ?string $district = null;

    public ?array $priced = null;

    public ?string $error = null;

    public function mount(Book $book): void
    {
        $this->book = $book;
        $this->delivery_name = auth()->user()?->name ?? '';
        $this->delivery_phone = auth()->user()?->phone ?? '';
        $this->refreshPrice();
    }

    public function updatedQuantity(): void
    {
        $this->refreshPrice();
    }

    public function refreshPrice(): void
    {
        try {
            $this->priced = app(PrintService::class)->priceOrder($this->book, $this->quantity);
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->priced = null;
            $this->error = $e->getMessage();
        }
    }

    public function submit(): void
    {
        $this->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'delivery_name' => 'required|string|max:255',
            'delivery_phone' => 'required|string|max:32',
            'delivery_address' => 'required|string|max:1000',
            'district' => 'nullable|string|max:100',
        ]);

        try {
            $order = app(PrintService::class)->createOrder(auth()->user(), $this->book, [
                'quantity' => $this->quantity,
                'delivery_name' => $this->delivery_name,
                'delivery_phone' => $this->delivery_phone,
                'delivery_address' => $this->delivery_address,
                'district' => $this->district,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->redirectRoute('print-orders.show', [
            'locale' => app()->getLocale(), 'printOrder' => $order->id,
        ]);
    }

    public function render()
    {
        return view('livewire.print-order-form');
    }
}
