<?php

namespace Tests\Feature\Author;

use App\Services\AuthorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class AuthorPagesTest extends AuthorTestCase
{
    #[DataProvider('authorPageProvider')]
    public function test_author_pages_render_in_both_locales(string $path): void
    {
        foreach (['bn', 'en'] as $locale) {
            $this->actingAuthor()
                ->get("/{$locale}{$path}")
                ->assertOk();
        }
    }

    public static function authorPageProvider(): array
    {
        return [
            'dashboard' => ['/author'],
            'publish' => ['/author/publish'],
            'books' => ['/author/books'],
            'reviews' => ['/author/reviews'],
            'promos' => ['/author/promos'],
            'settings' => ['/author/settings'],
            'notifications' => ['/notifications'],
            'support chat shell' => ['/support/chat'],
            'notification settings' => ['/profile/notifications'],
        ];
    }

    public function test_author_pages_are_noindex(): void
    {
        $this->actingAuthor()
            ->get('/bn/author')
            ->assertOk()
            ->assertSee('noindex, nofollow');

        $this->actingAuthor()
            ->get('/bn/author/publish')
            ->assertOk()
            ->assertSee('noindex, nofollow');
    }

    public function test_support_chat_shell_lists_my_tickets(): void
    {
        // No tickets yet — the shell shows the empty state and the
        // new-ticket form (Builder C's SupportTicketForm), not an error.
        $this->actingAuthor()
            ->get('/bn/support/chat')
            ->assertOk()
            ->assertSee(__('author.support_empty'));
    }

    public function test_notifications_page_renders_empty_state(): void
    {
        $this->actingAuthor()
            ->get('/bn/notifications')
            ->assertOk()
            ->assertSee(__('author.notifications_empty'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/bn/author')->assertRedirectContains('login');
        $this->get('/bn/author/apply')->assertRedirectContains('login');
    }

    public function test_dashboard_shows_frozen_seed_figures_when_earnings_exist(): void
    {
        if (! Schema::hasTable('author_earnings')) {
            $this->markTestSkipped('author_earnings table (Builder B) has not landed yet.');
        }

        // Frozen seed figures (§8.1): sales ৳48,640 + pool ৳636 = ৳49,276.
        DB::table('author_earnings')->insert([
            ['author_id' => $this->author->id, 'book_id' => null, 'order_id' => null, 'period' => '2026-11', 'type' => 'adjustment', 'amount_bdt' => 48640, 'share_pct' => 80, 'note' => 'demo opening balance (prototype frozen figures)', 'created_at' => now()],
            ['author_id' => $this->author->id, 'book_id' => null, 'order_id' => null, 'period' => '2026-11', 'type' => 'pool', 'amount_bdt' => 636, 'share_pct' => 80, 'note' => null, 'created_at' => now()],
        ]);

        $data = app(AuthorService::class)->dashboardData($this->author);

        $this->assertSame(49276, $data['total_bdt']);
        $this->assertSame(48640, $data['sales_bdt']);
        $this->assertSame(636, $data['pool_bdt']);

        $this->actingAuthor()
            ->get('/bn/author')
            ->assertOk()
            ->assertSee('49,276');
    }
}
