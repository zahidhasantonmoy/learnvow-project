<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Sanctum auth (spec §1.2–1.3): register / login / logout / me. */
class AuthController extends ApiController
{
    public function register(Request $request, AuthService $auth, DeviceService $devices)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $auth->register($data);
        $deviceName = $data['device_name'] ?? $this->defaultDeviceName($request);
        $token = $user->createToken($deviceName, ['app-access'], now()->addDays(365));

        try {
            $devices->registerApiDevice($user, $deviceName, $token->accessToken->id);
        } catch (DeviceLimitReachedException $e) {
            $token->accessToken->delete();

            return response()->json([
                'message' => __('api.device_limit_reached'),
                'error' => 'device_limit_reached',
                'devices' => $e->devices->map(fn ($d) => $this->deviceArray($d))->values(),
                'meta' => ['locale' => app()->getLocale()],
            ], 403);
        }

        return $this->ok([
            'user' => $this->userArray($user),
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    public function login(Request $request, AuthService $auth, DeviceService $devices)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        // SEC-02: strict limit is per phone+IP (5/min), like the web
        // LoginForm — the route middleware only caps per-IP (30/min).
        $key = 'api-login:'.Str::lower($data['phone']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too Many Attempts.',
                'meta' => ['locale' => app()->getLocale()],
            ], 429, ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        // AuthService::login() without the web-device hook (API registers
        // its own device below). Credentials + blocked-status checks live there.
        $result = $auth->login($data['phone'], $data['password'], false, false);
        if (! $result['ok']) {
            RateLimiter::hit($key, 60);

            return $this->fail(
                $result['error'] === 'blocked' ? __('api.account_blocked') : __('api.invalid_credentials'),
                $result['error'] === 'blocked' ? 403 : 401
            );
        }
        RateLimiter::clear($key);
        $user = $result['user'];

        $deviceName = $data['device_name'] ?? $this->defaultDeviceName($request);

        try {
            $token = $user->createToken($deviceName, ['app-access'], now()->addDays(365));
            $devices->registerApiDevice($user, $deviceName, $token->accessToken->id);
        } catch (DeviceLimitReachedException $e) {
            if (isset($token)) {
                $token->accessToken->delete();
            }

            return response()->json([
                'message' => __('api.device_limit_reached'),
                'error' => 'device_limit_reached',
                'devices' => $e->devices->map(fn ($d) => $this->deviceArray($d))->values(),
                'meta' => ['locale' => app()->getLocale()],
            ], 403);
        }

        return $this->ok([
            'user' => $this->userArray($user),
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(['logged_out' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $billing = app(\App\Services\BillingService::class);
        $subscription = $billing->currentSubscription($user);

        return $this->ok(array_merge($this->userArray($user), [
            'has_premium' => $billing->hasActivePremium($user),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_slug' => $subscription->plan?->slug,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'wallet_balance_bdt' => (int) ($user->wallet_balance_bdt ?? 0),
            'points_balance' => (int) ($user->points_balance ?? 0),
        ]));
    }

    /**
     * QA minor: API login used to default device_name to "Flutter".
     * Default to the request User-Agent instead ("API device" when the
     * client sends none), truncated to the validated 100-char max.
     */
    private function defaultDeviceName(Request $request): string
    {
        $agent = trim((string) ($request->userAgent() ?? ''));

        return mb_substr($agent !== '' ? $agent : 'API device', 0, 100);
    }

    private function userArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'locale' => $user->locale,
            'country' => $user->country,
            'status' => $user->status,
        ];
    }
}
