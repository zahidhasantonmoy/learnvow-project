<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\UserBook;
use App\Services\BookFileService;
use App\Services\DownloadEncryptionService;

class ReaderExtrasApiTest extends ApiTestCase
{
    public function test_collections_index_empty_until_builder_a_tables_land(): void
    {
        $this->asReader();
        // Builder A's collections table may or may not exist yet in this
        // shared tree — either way the endpoint must answer 200.
        $this->getJson('/api/v1/collections')->assertOk();
    }

    public function test_for_you_returns_popular_books_with_reason(): void
    {
        $this->asReader();
        $response = $this->getJson('/api/v1/for-you')->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('reason', $response->json('data.0'));
    }

    public function test_goals_badges_vocabulary_answer_200(): void
    {
        $this->asReader();
        $this->getJson('/api/v1/goals')->assertOk();
        $this->getJson('/api/v1/badges')->assertOk();
        $this->getJson('/api/v1/vocabulary')->assertOk();
    }

    public function test_devices_index_and_revoke(): void
    {
        $token = $this->reader->createToken('Phone', ['app-access'], now()->addDays(365));
        $device = Device::create([
            'user_id' => $this->reader->id, 'device_name' => 'Phone', 'device_type' => 'app',
            'last_active_at' => now(), 'api_token_id' => $token->accessToken->id,
        ]);
        $this->asReader();

        $this->getJson('/api/v1/devices')->assertOk()
            ->assertJsonPath('data.limit', 3)
            ->assertJsonPath('data.used', 1);

        // The current (Sanctum::actingAs) device cannot be revoked this way;
        // revoking the other device removes its token too.
        $this->deleteJson('/api/v1/devices/'.$device->id)->assertOk();
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.revoked']);
    }

    public function test_device_of_another_user_cannot_be_revoked(): void
    {
        $other = \App\Models\User::factory()->create();
        $device = Device::create([
            'user_id' => $other->id, 'device_name' => 'Phone', 'device_type' => 'app',
            'last_active_at' => now(),
        ]);
        $this->asReader();

        $this->deleteJson('/api/v1/devices/'.$device->id)->assertForbidden();
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    public function test_fcm_token_register_and_revoke(): void
    {
        $this->asReader();
        $this->postJson('/api/v1/fcm-token', ['token' => 'fcm-token-abc123', 'platform' => 'android'])
            ->assertCreated()->assertJsonPath('data.token_tail', '…abc123');
        $this->assertDatabaseHas('fcm_tokens', ['user_id' => $this->reader->id, 'platform' => 'android']);

        // Re-registering the same token is idempotent (one row).
        $this->postJson('/api/v1/fcm-token', ['token' => 'fcm-token-abc123', 'platform' => 'android'])->assertCreated();
        $this->assertSame(1, \App\Models\FcmToken::where('token', 'fcm-token-abc123')->count());

        $this->deleteJson('/api/v1/fcm-token', ['token' => 'fcm-token-abc123'])->assertOk();
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'fcm-token-abc123']);
    }

    public function test_notifications_list_and_mark_read(): void
    {
        $this->reader->notify(new \App\Notifications\AuthorNotification('order_live', ['order_no' => 'LV-2026-1041']));
        $this->asReader();

        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertNotEmpty($list->json('data'));
        $id = $list->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->assertNotNull(\Illuminate\Notifications\DatabaseNotification::find($id)->read_at);
    }

    public function test_print_orders_index_answers_200(): void
    {
        $this->asReader();
        // Builder C's print_orders table may not exist yet — 200 either way.
        $this->getJson('/api/v1/print-orders')->assertOk();
    }

    public function test_download_plain_then_encrypted_round_trip(): void
    {
        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase']);
        app(BookFileService::class)->storeDemoEpub($this->prophet, 'fake-epub-bytes');
        $this->asReader();

        $plain = $this->postJson('/api/v1/reader/the-prophet/download')->assertCreated()
            ->assertJsonPath('data.is_encrypted', false);
        $downloadId = $plain->json('data.download_id');
        $this->assertSame('fake-epub-bytes', $this->get('/api/v1/reader/the-prophet/download/'.$downloadId)->getContent());

        // Flip the setting on: new downloads are encrypted.
        \DB::table('settings')->updateOrInsert(['key' => 'protection.encrypted_downloads'], ['value' => '1']);
        $enc = $this->postJson('/api/v1/reader/the-prophet/download')->assertCreated()
            ->assertJsonPath('data.is_encrypted', true)
            ->assertJsonPath('data.encryption_key_id', 'hkdf-v1');
        $encId = $enc->json('data.download_id');

        $key = $this->getJson('/api/v1/reader/the-prophet/download-key')->assertOk()->json('data');
        $this->assertSame('AES-256-CBC', $key['algorithm']);

        $cipher = $this->get('/api/v1/reader/the-prophet/download/'.$encId)->getContent();
        $this->assertNotSame('fake-epub-bytes', $cipher);
        $decrypted = app(DownloadEncryptionService::class)->decrypt($cipher, $this->reader);
        $this->assertSame('fake-epub-bytes', $decrypted);
    }

    public function test_download_key_requires_access(): void
    {
        $this->asReader();
        $this->getJson('/api/v1/reader/the-prophet/download-key')->assertForbidden();
    }
}
