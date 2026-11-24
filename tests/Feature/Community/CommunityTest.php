<?php

namespace Tests\Feature\Community;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\CommunityPost;
use App\Models\Follow;
use App\Models\ReadingProgress;
use App\Models\Review;
use App\Models\User;
use App\Services\CommunityService;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 spec §6 — clubs, club posts, activity feed, moderation.
 */
class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected User $reader;

    protected User $reader2;

    protected User $support;

    protected User $admin;

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

        $this->reader2 = User::factory()->create(['phone' => '01700000003']);
        $this->reader2->assignRole('reader');

        $this->support = User::factory()->create(['phone' => '01700000013']);
        $this->support->assignRole('support');

        $this->admin = User::factory()->create(['phone' => '01700000011']);
        $this->admin->assignRole('admin');
    }

    public function test_club_create_join_post_via_web(): void
    {
        $this->actingAs($this->reader);

        $this->post('/bn/clubs', ['name' => 'Poetry Circle', 'description' => 'We read poems.', 'is_public' => true])
            ->assertRedirect();

        $club = Club::firstOrFail();
        $this->assertSame('Poetry Circle', $club->name);
        $this->assertTrue($club->hasMember($this->reader));
        $this->assertSame('creator', ClubMember::where('club_id', $club->id)->where('user_id', $this->reader->id)->first()->role);

        // Second reader joins.
        $this->actingAs($this->reader2);
        $this->post('/bn/clubs/'.$club->id.'/join')->assertRedirect();
        $this->assertTrue($club->fresh()->hasMember($this->reader2));

        // Member posts.
        $this->post('/bn/clubs/'.$club->id.'/posts', ['body' => 'Loved The Prophet!', 'type' => 'discussion'])
            ->assertRedirect();
        $this->assertSame(1, CommunityPost::where('club_id', $club->id)->count());

        $this->get('/bn/clubs/'.$club->id)->assertOk()->assertSee('Loved The Prophet!');
    }

    public function test_joining_a_club_awards_the_club_joiner_badge(): void
    {
        // Coordinator hook (Phase 4 §4.5): CommunityService awards the
        // club_joiner badge on join — and on create, since the creator
        // joins their own club. Award is idempotent (firstOrCreate).
        $this->seed(\Database\Seeders\BadgeSeeder::class);

        $club = app(CommunityService::class)->createClub($this->reader, [
            'name' => 'Badge Circle', 'is_public' => true,
        ]);

        $this->assertTrue(\App\Models\UserBadge::where('user_id', $this->reader->id)
            ->whereHas('badge', fn ($q) => $q->where('slug', 'club_joiner'))->exists());

        app(CommunityService::class)->joinClub($this->reader2, $club);

        $this->assertTrue(\App\Models\UserBadge::where('user_id', $this->reader2->id)
            ->whereHas('badge', fn ($q) => $q->where('slug', 'club_joiner'))->exists());
        $this->assertSame(1, \App\Models\UserBadge::where('user_id', $this->reader2->id)
            ->whereHas('badge', fn ($q) => $q->where('slug', 'club_joiner'))->count());
    }

    public function test_private_club_cannot_be_joined_without_invite(): void
    {
        $club = app(CommunityService::class)->createClub($this->reader, [
            'name' => 'Secret Circle', 'is_public' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(CommunityService::class)->joinClub($this->reader2, $club);
    }

    public function test_non_member_cannot_post_in_club(): void
    {
        $club = app(CommunityService::class)->createClub($this->reader, ['name' => 'Open Club']);

        $this->expectException(\InvalidArgumentException::class);
        app(CommunityService::class)->createPost($this->reader2, $club, ['body' => 'Hello']);
    }

    public function test_feed_with_club_post_renders_200(): void
    {
        // Regression (QA C1): feedFor() mapped rows to plain arrays and
        // then called Eloquent Collection::merge() on them — 500
        // "Call to a member function getKey() on array". The empty-feed
        // tests never hit it because merge only breaks with items.
        $club = app(CommunityService::class)->createClub($this->reader, ['name' => 'Feed Club']);
        app(CommunityService::class)->createPost($this->reader, $club, ['body' => 'Feed post body here']);

        $items = app(CommunityService::class)->feedFor($this->reader);
        $this->assertContains('club_post', collect($items)->pluck('kind')->all());

        $this->actingAs($this->reader);
        $this->get('/bn/community')->assertOk()->assertSee('Feed post body here');
    }

    public function test_feed_shows_followed_users_activity(): void
    {
        $prophet = \App\Models\Book::where('slug', 'the-prophet')->firstOrFail();
        $author = \App\Models\Author::where('slug', 'kahlil-gibran')->firstOrFail();

        // reader2 follows Gibran; Gibran's user is the author user — use
        // reader2 as the followed author by linking the author row.
        $author->update(['user_id' => $this->reader2->id]);
        Follow::create(['user_id' => $this->reader->id, 'author_id' => $author->id]);

        ReadingProgress::create([
            'user_id' => $this->reader2->id, 'book_id' => $prophet->id,
            'percent' => 100, 'finished_at' => now(),
        ]);
        Review::create([
            'user_id' => $this->reader2->id, 'book_id' => $prophet->id,
            'rating' => 5, 'title' => 'Great', 'body' => 'Loved it.', 'status' => 'approved',
        ]);

        $items = app(CommunityService::class)->feedFor($this->reader);

        $kinds = collect($items)->pluck('kind')->all();
        $this->assertContains('finished', $kinds);
        $this->assertContains('review', $kinds);

        $this->actingAs($this->reader);
        $this->get('/bn/community')->assertOk()->assertSee('The Prophet');
    }

    public function test_reported_post_hidden_by_support(): void
    {
        $club = app(CommunityService::class)->createClub($this->reader, ['name' => 'Open Club']);
        app(CommunityService::class)->joinClub($this->reader2, $club);
        $post = app(CommunityService::class)->createPost($this->reader2, $club, ['body' => 'Spammy post']);

        // Another member reports it; Support hides it.
        $report = app(CommunityService::class)->reportPost($this->reader, $post, 'spam', 'Looks like spam.');
        $this->assertSame('community_posts', $report->reportable_type);

        $hidden = app(CommunityService::class)->hidePost($this->support, $post->fresh());
        $this->assertSame('hidden', $hidden->status);

        // Hidden post no longer appears on the club page.
        $this->actingAs($this->reader);
        $this->get('/bn/clubs/'.$club->id)->assertOk()->assertDontSee('Spammy post');

        // Admin restores it.
        $restored = app(CommunityService::class)->restorePost($this->admin, $hidden->fresh());
        $this->assertSame('published', $restored->status);
    }

    public function test_club_post_report_hide_restore_round_trip(): void
    {
        // Regression (QA M4): report/hide/restore existed in the service
        // but had zero UI callers and decideReport ignored community_posts.
        $club = app(CommunityService::class)->createClub($this->reader, ['name' => 'Report Club']);
        app(CommunityService::class)->joinClub($this->reader2, $club);
        $post = app(CommunityService::class)->createPost($this->reader2, $club, ['body' => 'Reportable post']);

        // Report via the web route (button on the club page).
        $this->actingAs($this->reader);
        $this->get('/bn/clubs/'.$club->id)->assertOk()->assertSee('রিপোর্ট করুন');
        $this->post("/bn/clubs/{$club->id}/posts/{$post->id}/report", ['reason' => 'spam', 'details' => 'Looks like spam.'])
            ->assertRedirect();
        $report = \App\Models\Report::where('reportable_type', 'community_posts')->firstOrFail();
        $this->assertSame($post->id, $report->reportable_id);
        $this->assertSame('open', $report->status);

        // Support hides it; Admin dismisses the report → post restored.
        app(CommunityService::class)->hidePost($this->support, $post->fresh());
        $this->assertSame('hidden', $post->fresh()->status);
        $this->get('/bn/clubs/'.$club->id)->assertOk()->assertDontSee('Reportable post');

        app(\App\Services\AdminService::class)->decideReport($report->fresh(), 'dismiss', $this->admin, 'Not spam.');
        $this->assertSame('published', $post->fresh()->status);
        $this->assertSame('dismissed', $report->fresh()->status);

        // Second report + hide + Admin "action" deletes the post.
        $report2 = app(CommunityService::class)->reportPost($this->reader, $post->fresh(), 'abuse');
        app(CommunityService::class)->hidePost($this->support, $post->fresh());
        app(\App\Services\AdminService::class)->decideReport($report2->fresh(), 'action', $this->admin, 'Confirmed.');
        $this->assertNull(\App\Models\CommunityPost::find($post->id));
        $this->assertSame('action_taken', $report2->fresh()->status);
    }

    public function test_find_friends_without_search_shows_hint(): void
    {
        // QA minor: no search showed an empty state with no guidance.
        $this->actingAs($this->reader);
        $this->get('/bn/find-friends')
            ->assertOk()
            ->assertSee('বন্ধুর নাম লিখে খুঁজুন');
        $this->get('/en/find-friends')
            ->assertOk()
            ->assertSee('Type a friend’s name to search');
    }

    public function test_find_friends_searches_by_name_only(): void
    {
        $this->actingAs($this->reader);
        $response = $this->get('/bn/find-friends?q='.urlencode($this->reader2->name));
        $response->assertOk()->assertSee($this->reader2->name);
        // Phone numbers are never shown in search results.
        $response->assertDontSee($this->reader2->phone);
    }

    public function test_livewire_club_discussion_posts(): void
    {
        $club = app(CommunityService::class)->createClub($this->reader, ['name' => 'Livewire Club']);
        $this->actingAs($this->reader);

        \Livewire\Livewire::test(\App\Livewire\ClubDiscussion::class, ['club' => $club])
            ->set('body', 'Hello from Livewire!')
            ->call('post')
            ->assertSee('Hello from Livewire!');

        $this->assertSame(1, CommunityPost::where('club_id', $club->id)->count());
    }
}
