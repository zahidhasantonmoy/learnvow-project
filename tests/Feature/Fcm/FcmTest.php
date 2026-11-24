<?php

namespace Tests\Feature\Fcm;

use App\Models\FcmToken;
use App\Models\User;
use App\Notifications\AuthorNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use Tests\TestCase;

class FcmTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    private FcmService $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = User::factory()->create();
        $this->fcm = app(FcmService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_and_revoke_tokens(): void
    {
        $row = $this->fcm->registerToken($this->reader, 'tok-aaa111', 'android');
        $this->assertSame('tok-aaa111', $row->token);
        $this->assertSame('…aaa111', $row->maskedToken());

        $this->fcm->revokeToken($this->reader, 'tok-aaa111');
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'tok-aaa111']);
    }

    public function test_send_noops_when_disabled(): void
    {
        // No fcm.enabled setting in this test DB → disabled by default.
        $this->assertFalse($this->fcm->enabled());
        $this->fcm->registerToken($this->reader, 'tok-aaa111', 'android');

        $this->fcm->sendToUser($this->reader, 'Title', 'Body');
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'tok-aaa111']);
    }

    public function test_send_noops_when_enabled_but_no_credentials(): void
    {
        \DB::table('settings')->updateOrInsert(['key' => 'fcm.enabled'], ['value' => '1']);
        $this->assertTrue($this->fcm->enabled());
        $this->fcm->registerToken($this->reader, 'tok-aaa111', 'android');

        // No Firebase project/credentials in tests — must not throw.
        $this->fcm->sendToUser($this->reader, 'Title', 'Body');
        $this->assertTrue(true);
    }

    public function test_invalid_token_is_cleaned_up_after_not_found(): void
    {
        \DB::table('settings')->updateOrInsert(['key' => 'fcm.enabled'], ['value' => '1']);
        $this->fcm->registerToken($this->reader, 'dead-token-1', 'ios');

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->andThrow(
            new \Exception('Requested entity was not found.')
        );
        $this->app->instance(Messaging::class, $messaging);

        $this->fcm->sendToUser($this->reader, 'Title', 'Body');
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'dead-token-1']);
    }

    public function test_should_send_requires_enabled_token_and_settings(): void
    {
        $this->assertFalse($this->fcm->shouldSend($this->reader, 'payout_approved'));

        \DB::table('settings')->updateOrInsert(['key' => 'fcm.enabled'], ['value' => '1']);
        $this->assertFalse($this->fcm->shouldSend($this->reader, 'payout_approved'));

        $this->fcm->registerToken($this->reader, 'tok-aaa111', 'android');
        $this->assertTrue($this->fcm->shouldSend($this->reader, 'payout_approved'));

        // Opting out of payouts in notification_settings blocks FCM for that type.
        \App\Models\NotificationSetting::forUser($this->reader)->update(['payouts' => false]);
        $this->assertFalse($this->fcm->shouldSend($this->reader, 'payout_approved'));
        $this->assertTrue($this->fcm->shouldSend($this->reader, 'ticket_reply'));
    }

    public function test_author_notification_includes_fcm_channel_only_when_allowed(): void
    {
        $notification = new AuthorNotification('payout_approved', ['period' => '2026-11', 'total_bdt' => 1000]);
        $this->assertSame(['database'], $notification->via($this->reader));

        \DB::table('settings')->updateOrInsert(['key' => 'fcm.enabled'], ['value' => '1']);
        $this->fcm->registerToken($this->reader, 'tok-aaa111', 'android');
        $this->assertContains(\App\Notifications\Channels\FcmChannel::class, $notification->via($this->reader));

        $payload = $notification->toFcm($this->reader);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertSame('payout_approved', $payload['data']['type']);
    }
}
