<?php

namespace Tests\Feature\Print;

use App\Models\Coupon;
use App\Models\Setting;
use App\Services\AdminService;
use App\Services\AuthorService;
use InvalidArgumentException;
use Tests\Feature\Author\AuthorTestCase;

/**
 * K1 — Promo guardrail regression tests (Phase 4 spec §10).
 *
 * Decision 4 wins over Phase 3 §1.9/A12: an unset promo.max_percent /
 * promo.max_duration_days means NO limit (not "blocked"), and a discount
 * may be up to 100%, never above. These tests were written BEFORE the
 * AuthorService change (tests first, code second) and must fail on the
 * Phase 3 behaviour.
 */
class PromoGuardrailTest extends AuthorTestCase
{
    protected \App\Models\Book $ownBook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownBook = app(AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );
        app(AdminService::class)->approveBook($this->ownBook);
        $this->ownBook = $this->ownBook->fresh();
    }

    private function promoData(array $overrides = []): array
    {
        return array_merge([
            'book_id' => $this->ownBook->id,
            'code' => 'TEST'.strtoupper(\Illuminate\Support\Str::random(6)),
            'percent' => 20,
            'duration_days' => 7,
        ], $overrides);
    }

    public function test_no_limits_set_means_no_limit_author_can_create_promo(): void
    {
        // No promo.* settings at all: Phase 3 blocked this; Phase 4 allows it.
        $this->assertNull(Setting::where('key', 'promo.max_percent')->value('value'));
        $this->assertNull(Setting::where('key', 'promo.max_duration_days')->value('value'));

        $service = app(AuthorService::class);

        $this->assertSame([null, null], array_values($service->promoLimits()));
        $this->assertTrue($service->canCreatePromo());

        $coupon = $service->createPromo($this->author, $this->promoData());

        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertSame(20, $coupon->percent);
        $this->assertSame($this->ownBook->id, $coupon->book_id);
    }

    public function test_limit_set_is_enforced(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '20']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '14']);

        $service = app(AuthorService::class);

        $this->assertSame(['max_percent' => 20, 'max_duration_days' => 14], $service->promoLimits());
        $this->assertTrue($service->canCreatePromo());

        // 20% (at the limit) is fine.
        $ok = $service->createPromo($this->author, $this->promoData(['code' => 'ATLIMIT20']));
        $this->assertSame(20, $ok->percent);

        // 25% (over the limit) is rejected.
        $this->expectException(InvalidArgumentException::class);
        $service->createPromo($this->author, $this->promoData(['code' => 'OVERLIMIT', 'percent' => 25]));
    }

    public function test_duration_limit_set_is_enforced(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '50']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '14']);

        $this->expectException(InvalidArgumentException::class);
        app(AuthorService::class)->createPromo(
            $this->author, $this->promoData(['duration_days' => 30])
        );
    }

    public function test_discount_up_to_100_percent_allowed_when_no_limit(): void
    {
        $coupon = app(AuthorService::class)->createPromo(
            $this->author, $this->promoData(['code' => 'FREE100', 'percent' => 100])
        );

        $this->assertSame(100, $coupon->percent);
    }

    public function test_discount_above_100_percent_is_never_allowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(AuthorService::class)->createPromo(
            $this->author, $this->promoData(['percent' => 101])
        );
    }

    public function test_discount_above_100_percent_rejected_even_with_limit_100(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '100']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '30']);

        $this->expectException(InvalidArgumentException::class);
        app(AuthorService::class)->createPromo(
            $this->author, $this->promoData(['percent' => 101])
        );
    }
}
