<?php

namespace App\Services;

use App\Models\Device;
use App\Models\FcmToken;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * FCM push — Flutter app only, no email/SMS (spec §8).
 * MUST no-op gracefully when fcm.enabled=0 or credentials are missing;
 * sending never throws in production paths.
 */
class FcmService
{
    public function enabled(): bool
    {
        $value = null;
        if (Schema::hasTable('settings')) {
            $value = DB::table('settings')->where('key', 'fcm.enabled')->value('value');
        }

        return $value === '1';
    }

    public function registerToken(User $user, string $token, string $platform, ?Device $device = null): FcmToken
    {
        $token = trim($token);

        // One token belongs to one user: a re-registered token moves owners.
        $existing = FcmToken::where('token', $token)->first();
        if ($existing !== null) {
            $existing->update([
                'user_id' => $user->id,
                'device_id' => $device?->id ?? $existing->device_id,
                'platform' => $platform,
            ]);

            return $existing->fresh();
        }

        return FcmToken::create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'token' => $token,
            'platform' => $platform,
        ]);
    }

    public function revokeToken(User $user, ?string $token = null): void
    {
        $query = FcmToken::where('user_id', $user->id);
        if ($token !== null) {
            $query->where('token', trim($token));
        }
        $query->delete();
    }

    /**
     * Should this notification type go to FCM for this user? All three
     * must hold (spec §8.3): fcm.enabled=1, ≥1 valid token, and the
     * notification_settings row allows the type.
     */
    public function shouldSend(User $user, string $type): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        if (! Schema::hasTable('fcm_tokens') || $user->fcmTokens()->count() === 0) {
            return false;
        }

        return $this->typeAllowed($user, $type);
    }

    /** Map an AuthorNotification type to its notification_settings column. */
    public function typeAllowed(User $user, string $type): bool
    {
        $column = match ($type) {
            'payout_approved', 'payout_released', 'payout_failed' => 'payouts',
            'book_approved', 'book_rejected', 'application_approved', 'application_rejected', 'share_changed' => 'approvals',
            'ticket_reply' => 'tickets',
            'order_live', 'subscription_past_due', 'print_status' => 'announcements',
            default => null,
        };

        if ($column === null) {
            return true;
        }
        if (! Schema::hasTable('notification_settings')) {
            return true;
        }

        // forUser() firstOrCreate leaves DB-default booleans null on a
        // freshly created model — reload so the real value is read.
        $settings = NotificationSetting::forUser($user)->fresh() ?? NotificationSetting::forUser($user);

        return (bool) $settings->{$column};
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled() || ! Schema::hasTable('fcm_tokens')) {
            return;
        }

        $tokens = $user->fcmTokens()->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToDevice(Device $device, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled() || ! Schema::hasTable('fcm_tokens')) {
            return;
        }

        $tokens = FcmToken::where('device_id', $device->id)->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    /** Send to raw tokens; invalid (NotFound) tokens are deleted. Never throws. */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if ($tokens === [] || ! $this->enabled()) {
            return;
        }

        try {
            $messaging = app(Messaging::class);
        } catch (\Throwable $e) {
            Log::warning('FCM unavailable (no credentials/project) — push skipped.', ['error' => $e->getMessage()]);

            return;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::new()
                    ->withToken($token)
                    ->withNotification(FcmNotification::create($title, $body))
                    ->withData($stringData);
                $messaging->send($message);
            } catch (\Throwable $e) {
                // FCM "Requested entity was not found" = dead token: clean it up.
                if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'NotFound')) {
                    FcmToken::where('token', $token)->delete();
                    Log::info('FCM invalid token removed.', ['token_tail' => substr($token, -6)]);
                } else {
                    Log::warning('FCM send failed.', ['error' => $e->getMessage(), 'token_tail' => substr($token, -6)]);
                }
            }
        }
    }
}
