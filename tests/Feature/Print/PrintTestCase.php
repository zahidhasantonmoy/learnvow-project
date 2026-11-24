<?php

namespace Tests\Feature\Print;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class PrintTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $reader;

    protected User $authorUser;

    protected Author $author;

    protected Book $prophet;

    protected User $partner;

    protected User $admin;

    protected User $superAdmin;

    protected User $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([
            RolePermissionSeeder::class, CategorySeeder::class,
            AuthorSeeder::class, BookSeeder::class,
        ]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->reader->assignRole('reader');

        $this->authorUser = User::factory()->create(['phone' => '01700000003', 'name' => 'Demo Author']);
        $this->authorUser->assignRole('reader');
        $this->authorUser->assignRole('author');

        $this->author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();
        $this->author->update(['user_id' => $this->authorUser->id, 'status' => 'approved']);

        // Prophet: print price ৳454 so the spec worked example
        // (price ৳454, partner cost ৳200 → author share ৳203) is testable.
        $this->prophet = Book::where('slug', 'the-prophet')->firstOrFail();
        $this->prophet->update([
            'print_price_bdt' => 454,
            'allow_pod' => true,
            'allow_licensed_pdf' => true,
            'status' => 'published',
            'is_paused' => false,
        ]);
        // Prophet's author is Kahlil Gibran in the seed, not the demo
        // author — point the book at the demo author for share tests.
        $this->prophet->update(['author_id' => $this->author->id]);

        $this->partner = User::factory()->create(['phone' => '01700000010', 'name' => 'Print Partner']);
        $this->partner->assignRole('print-partner');

        $this->admin = User::factory()->create(['phone' => '01700000011']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['phone' => '01700000012']);
        $this->superAdmin->assignRole('super-admin');

        $this->support = User::factory()->create(['phone' => '01700000013']);
        $this->support->assignRole('support');
    }

    protected function orderData(array $overrides = []): array
    {
        return array_merge([
            'quantity' => 1,
            'delivery_name' => 'Test Reader',
            'delivery_phone' => '01700000002',
            'delivery_address' => '1 Test Road, Dhaka',
            'district' => 'Dhaka',
        ], $overrides);
    }

    /** Drive an order all the way to delivered via the service. */
    protected function deliveredOrder(array $overrides = []): \App\Models\PrintOrder
    {
        $prints = app(\App\Services\PrintService::class);

        $order = $prints->createOrder($this->reader, $this->prophet, $this->orderData($overrides));
        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);
        $order = $prints->markPrinting($order->fresh(), $this->partner);
        $order = $prints->markShipped($order->fresh(), $this->partner, 'TRACK123');

        return $prints->markDelivered($order->fresh(), $this->admin);
    }
}
