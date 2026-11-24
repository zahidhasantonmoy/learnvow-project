<?php

namespace Tests\Feature\Serials;

use App\Models\Author;
use App\Models\Serial;
use App\Models\SerialEpisode;
use App\Services\AdminService;
use Tests\Feature\Reader\ReaderTestCase;

class SerialTest extends ReaderTestCase
{
    protected Author $author;

    protected Serial $serial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = Author::where('slug', 'rabindranath-tagore')->firstOrFail();
        $this->serial = Serial::create([
            'author_id' => $this->author->id,
            'title' => 'ধারাবাহিক গল্প',
            'status' => 'ongoing',
            'is_free' => true,
        ]);
    }

    public function test_author_creates_serial_and_adds_episode_via_studio(): void
    {
        // Regression (QA M1): no AuthorSerialController existed — an
        // author had no create path and the serials table was empty.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->reader->assignRole('author');
        $author = Author::create([
            'user_id' => $this->reader->id, 'name' => 'Studio Author',
            'slug' => 'studio-author', 'status' => 'approved',
        ]);

        $this->actingAs($this->reader);
        $this->get('/bn/author')->assertOk()->assertSee('আমার ধারাবাহিক');
        $this->get('/bn/author/serials')->assertOk();
        $this->get('/bn/author/serials/create')->assertOk();

        $response = $this->post('/bn/author/serials', [
            'title' => 'আমার ধারাবাহিক', 'description' => 'গল্পের ধারা।',
        ]);
        $response->assertRedirect();
        $serial = Serial::where('title', 'আমার ধারাবাহিক')->firstOrFail();
        $this->assertSame($author->id, $serial->author_id);

        // Manage page renders the episode manager; adding an episode works.
        $this->get("/bn/author/serials/{$serial->id}")->assertOk()->assertSee('পর্ব যোগ করুন');
        \Livewire\Livewire::test(\App\Livewire\SerialEpisodeManager::class, ['serial' => $serial])
            ->set('title', 'পর্ব ১')
            ->set('content', 'লেখা।')
            ->call('addEpisode')
            ->assertHasNoErrors();
        $this->assertSame(1, SerialEpisode::where('serial_id', $serial->id)->count());

        // Admin approval still works on an author-created serial.
        $admin = $this->otherReader();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');
        app(AdminService::class)->approveSerial($serial->fresh(), $admin);
        $this->assertNotNull($serial->fresh()->approved_at);
    }

    public function test_reader_without_serials_publish_cannot_create_serial(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->actingAs($this->reader);
        $this->post('/bn/author/serials', ['title' => 'Nope'])->assertForbidden();
        $this->get('/bn/author/serials')->assertForbidden();
    }

    public function test_seeded_demo_serial_detail_renders(): void
    {
        $this->seed(\Database\Seeders\SerialSeeder::class);
        $serial = Serial::where('title', 'ধারাবাহিক: প্রথম গল্প')->firstOrFail();
        $this->assertSame(2, $serial->episodes()->count());

        $this->actingAs($this->reader);
        $this->get("/bn/serials/{$serial->id}")
            ->assertOk()
            ->assertSee('প্রথম পর্ব')
            ->assertSee('দ্বিতীয় পর্ব');
    }

    public function test_serial_show_lists_episodes_and_subscribe_toggles(): void
    {
        $episode = SerialEpisode::create([
            'serial_id' => $this->serial->id, 'episode_no' => 1,
            'title' => 'প্রথম পর্ব', 'content' => 'গল্পের শুরু।',
            'published_at' => now()->subHour(), 'is_free' => true,
        ]);
        SerialEpisode::create([
            'serial_id' => $this->serial->id, 'episode_no' => 2,
            'title' => 'দ্বিতীয় পর্ব', 'content' => 'আসছে।',
            'published_at' => null, 'is_free' => true,
        ]);

        $this->actingAs($this->reader);
        $this->get("/bn/serials/{$this->serial->id}")
            ->assertOk()
            ->assertSee('প্রথম পর্ব')
            ->assertSee('দ্বিতীয় পর্ব');

        // Published episode reads; unpublished 404s.
        $this->get("/bn/serials/{$this->serial->id}/episodes/{$episode->id}")
            ->assertOk()->assertSee('গল্পের শুরু।');

        // Subscribe = follow the author; again = unsubscribe.
        $this->post("/bn/serials/{$this->serial->id}/notify")->assertRedirect();
        $this->assertDatabaseHas('follows', ['user_id' => $this->reader->id, 'author_id' => $this->author->id]);
        $this->post("/bn/serials/{$this->serial->id}/notify")->assertRedirect();
        $this->assertDatabaseMissing('follows', ['user_id' => $this->reader->id, 'author_id' => $this->author->id]);
    }

    public function test_episode_publish_notifies_followers(): void
    {
        $this->actingAs($this->reader);
        $this->post("/bn/serials/{$this->serial->id}/notify")->assertRedirect();

        $episode = SerialEpisode::create([
            'serial_id' => $this->serial->id, 'episode_no' => 1,
            'title' => 'প্রথম পর্ব', 'content' => 'গল্প।',
            'published_at' => now(), 'is_free' => true,
        ]);

        \App\Http\Controllers\SerialController::notifyFollowers($this->serial, $episode);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->reader->id,
            'notifiable_type' => \App\Models\User::class,
        ]);
    }

    public function test_admin_approve_serial_records_approval(): void
    {
        $admin = $this->otherReader();
        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        app(AdminService::class)->approveSerial($this->serial, $admin);

        $this->assertNotNull($this->serial->fresh()->approved_at);
        $this->assertEquals($admin->id, $this->serial->fresh()->approved_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'serial.approved', 'subject_id' => $this->serial->id]);
    }

    public function test_non_admin_cannot_approve_serial(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(AdminService::class)->approveSerial($this->serial, $this->reader);
    }

    public function test_serial_episode_manager_adds_and_publishes(): void
    {
        // The author owns the serial: give the reader an author row.
        $author = Author::create([
            'user_id' => $this->reader->id, 'name' => 'Test Author',
            'slug' => 'test-author', 'status' => 'approved',
        ]);
        $serial = Serial::create(['author_id' => $author->id, 'title' => 'My Serial', 'is_free' => true]);

        $this->actingAs($this->reader);
        \Livewire\Livewire::test(\App\Livewire\SerialEpisodeManager::class, ['serial' => $serial])
            ->set('title', 'পর্ব ১')
            ->set('content', 'লেখা।')
            ->call('addEpisode')
            ->assertHasNoErrors();

        $episode = SerialEpisode::where('serial_id', $serial->id)->firstOrFail();
        $this->assertEquals(1, $episode->episode_no);
        $this->assertNotNull($episode->published_at);
    }
}
