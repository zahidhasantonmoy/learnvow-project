<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

/** Laravel custom channel: delivers a notification via FCM (spec §8.3). */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        app(FcmService::class)->sendToUser($notifiable, $title, $body, $data);
    }
}
