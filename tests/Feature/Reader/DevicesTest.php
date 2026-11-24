<?php

namespace Tests\Feature\Reader;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devices screen (§4.1/§5.1). The devices table + DeviceService are
 * Builder B's and have not landed — the table is created here to the
 * spec §12.1.2 schema when missing, so the screen's limit-3 math can
 * be verified; the screen itself must render empty without the table.
 */
class DevicesTest extends ReaderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_name');
                $table->enum('device_type', ['web', 'app']);
                $table->string('platform')->nullable();
                $table->string('last_ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->bigInteger('api_token_id')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function addDevice(string $name, string $type = 'web', ?string $lastActive = null): int
    {
        return (int) DB::table('devices')->insertGetId([
            'user_id' => $this->reader->id,
            'device_name' => $name,
            'device_type' => $type,
            'platform' => 'Chrome — Windows',
            'last_active_at' => $lastActive ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_devices_screen_renders_with_limit_three_math(): void
    {
        $this->addDevice('Chrome — Windows');
        $this->addDevice('Mi Pad 7 — Flutter', 'app');

        $this->actingAs($this->reader);
        $response = $this->get('/bn/profile/devices');
        $response->assertOk()
            ->assertSee('3 টির মধ্যে 2 টি ব্যবহৃত')
            ->assertSee('Chrome — Windows')
            ->assertSee('Mi Pad 7 — Flutter');

        // QA minor: the "N of 3 used" line was printed twice (blade +
        // Livewire device-manager). It must appear exactly once now.
        $this->assertSame(1, substr_count($response->getContent(), '3 টির মধ্যে 2 টি ব্যবহৃত'));
    }

    public function test_devices_screen_renders_empty_without_devices(): void
    {
        $this->actingAs($this->reader);
        $this->get('/bn/profile/devices')
            ->assertOk()
            ->assertSee('3 টির মধ্যে 0 টি ব্যবহৃত');
    }

    public function test_device_removal_deletes_own_device_only(): void
    {
        $mine = $this->addDevice('Chrome — Windows');
        $other = $this->otherReader();
        $theirs = (int) DB::table('devices')->insertGetId([
            'user_id' => $other->id,
            'device_name' => 'Other Phone',
            'device_type' => 'app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->reader);
        $this->delete("/bn/profile/devices/{$mine}")->assertRedirect();
        $this->assertDatabaseMissing('devices', ['id' => $mine]);

        // Someone else's device: 404, row stays.
        $this->delete("/bn/profile/devices/{$theirs}")->assertNotFound();
        $this->assertDatabaseHas('devices', ['id' => $theirs]);
    }

    public function test_inactive_device_flagged_after_90_days(): void
    {
        $this->addDevice('Old Laptop', 'web', now()->subDays(100)->toString());

        $this->actingAs($this->reader);
        $this->get('/bn/profile/devices')->assertOk()->assertSee('৯০+ দিন নিষ্ক্রিয়');
    }
}
