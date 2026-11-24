<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

class AuthApiTest extends ApiTestCase
{
    public function test_register_returns_token_with_365_day_expiry(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Reader', 'phone' => '01711111111',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'device_name' => 'Mi Pad 7 — Flutter',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'phone'], 'token', 'token_expires_at']]);

        $user = User::where('phone', '01711111111')->firstOrFail();
        $token = $user->tokens()->firstOrFail();
        $this->assertSame('Mi Pad 7 — Flutter', $token->name);
        $this->assertTrue($token->expires_at->isAfter(now()->addDays(364)));
        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'device_type' => 'app']);
    }

    public function test_login_returns_token_and_me_works(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '01700000002', 'password' => 'password', 'device_name' => 'Mi Pad 7 — Flutter',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['user' => ['id', 'phone'], 'token']]);
        $token = $response->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', '01700000002')
            ->assertJsonPath('data.has_premium', false)
            ->assertJsonPath('data.wallet_balance_bdt', 0);
    }

    public function test_bad_token_is_401(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/v1/books')->assertUnauthorized();
        $this->getJson('/api/v1/library')->assertUnauthorized();
    }

    public function test_logout_kills_the_token(): void
    {
        $token = $this->apiTokenFor($this->reader);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->reader->id]);

        // The guard caches the resolved user inside one test process —
        // forget it so the next request re-authenticates from the header.
        auth()->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_api_login_defaults_device_name_to_user_agent(): void
    {
        // QA minor: login/register defaulted device_name to "Flutter".
        $response = $this->withHeader('User-Agent', 'TestAgent/1.0')
            ->postJson('/api/v1/auth/login', [
                'phone' => '01700000002', 'password' => 'password',
            ]);
        $response->assertOk();

        $token = $this->reader->tokens()->firstOrFail();
        $this->assertSame('TestAgent/1.0', $token->name);
        $this->assertDatabaseHas('devices', [
            'user_id' => $this->reader->id, 'device_name' => 'TestAgent/1.0',
        ]);
    }

    public function test_api_error_envelope_includes_meta_locale(): void
    {
        // Regression (QA M6): successes are {data, meta:{locale}} but
        // errors were a bare {message}. Failed login must carry meta too.
        $response = $this->withHeader('Accept-Language', 'bn')
            ->postJson('/api/v1/auth/login', [
                'phone' => '01700000002', 'password' => 'wrong',
            ]);
        $response->assertUnauthorized()
            ->assertJsonPath('meta.locale', 'bn')
            ->assertJsonStructure(['message', 'meta' => ['locale']]);
    }

    public function test_login_rate_limit_returns_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['phone' => '01700000002', 'password' => 'wrong']);
        }
        $this->postJson('/api/v1/auth/login', ['phone' => '01700000002', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_login_throttle_allows_five_attempts_per_minute(): void
    {
        // Repro (QA N2): group throttle:60,1 + route throttle:5,1 shared
        // one counter, so each login consumed 2 hits and the 3rd attempt
        // was already 429. Spec §1.3: exactly 5/min for login.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['phone' => '01700000002', 'password' => 'wrong'])
                ->assertUnauthorized()
                ->assertHeader('X-RateLimit-Limit', '5')
                ->assertHeader('X-RateLimit-Remaining', (string) (4 - $i));
        }
        $this->postJson('/api/v1/auth/login', ['phone' => '01700000002', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_throttle_429_uses_error_envelope(): void
    {
        // Repro (QA N3): the 429 body was a bare {"message": ...} with no
        // meta:{locale}, unlike the enveloped 401 from a wrong password.
        $response = null;
        for ($i = 0; $i < 6; $i++) {
            $response = $this->withHeader('Accept-Language', 'bn')
                ->postJson('/api/v1/auth/login', ['phone' => '01700000002', 'password' => 'wrong']);
        }
        $response->assertStatus(429)
            ->assertJsonPath('message', 'Too Many Attempts.')
            ->assertJsonPath('meta.locale', 'bn');
    }

    public function test_fourth_device_login_is_blocked_with_403(): void
    {
        // Three devices already registered for this reader.
        foreach (['Phone', 'Tablet', 'Laptop'] as $name) {
            $token = $this->reader->createToken($name, ['app-access'], now()->addDays(365));
            \App\Models\Device::create([
                'user_id' => $this->reader->id, 'device_name' => $name, 'device_type' => 'app',
                'last_active_at' => now(), 'api_token_id' => $token->accessToken->id,
            ]);
        }

        $this->withHeader('Accept-Language', 'bn')
            ->postJson('/api/v1/auth/login', [
                'phone' => '01700000002', 'password' => 'password', 'device_name' => 'New Phone',
            ])->assertForbidden()->assertJsonPath('error', 'device_limit_reached')
            ->assertJsonPath('meta.locale', 'bn');

        // Same device (same token name is not the key — a fresh login is a
        // new device) is blocked; but an existing device re-login is not
        // part of this flow. The 3 devices remain.
        $this->assertSame(3, \App\Models\Device::where('user_id', $this->reader->id)->count());
    }

    public function test_api_response_shape_and_bn_locale(): void
    {
        $this->asReader();
        $this->getJson('/api/v1/books', ['Accept-Language' => 'bn'])
            ->assertOk()
            ->assertJsonPath('meta.locale', 'bn')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
