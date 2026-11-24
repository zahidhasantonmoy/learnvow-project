<?php

namespace App\Notifications;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (spec §6) — in-app only (database channel, A13). Never carries
 * sensitive data: no full bKash number, no TrxID, no money beyond a
 * plain amount when the event is about one.
 */
class AuthorNotification extends Notification
{
    use Queueable;

    public function __construct(public string $type, public array $data = []) {}

    /**
     * Phase 4 (§8.3): database always; 'fcm' only when FcmService says
     * the user may receive this type (enabled + has a token + settings
     * allow it). FcmService never throws here.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        try {
            if (app(\App\Services\FcmService::class)->shouldSend($notifiable, $this->type)) {
                $channels[] = \App\Notifications\Channels\FcmChannel::class;
            }
        } catch (\Throwable) {
            // FCM must never break the in-app notification.
        }

        return $channels;
    }

    /** FCM payload (spec §8.3): title/body from describe(), never a TrxID or full bKash number. */
    public function toFcm(object $notifiable): array
    {
        $described = static::describe(array_merge(['type' => $this->type], $this->data));

        return [
            'title' => __('author.notifications.title'),
            'body' => $described['text'],
            'data' => [
                'type' => $this->type,
                'url' => $described['url'] ?? '',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(['type' => $this->type], $this->data);
    }

    /**
     * Human-readable rendering of a stored notification (round 2 minor):
     * one sentence per event type from the lang files, using the
     * payload's data (title / amount / ticket no. / order no.), plus a
     * link to the relevant page. Unknown types fall back to a generic
     * sentence — the raw event key is never shown.
     *
     * @return array{text: string, url: string|null}
     */
    public static function describe(array $data): array
    {
        $type = (string) ($data['type'] ?? 'unknown');

        $known = [
            'book_approved', 'book_rejected',
            'application_approved', 'application_rejected',
            'payout_approved', 'payout_released', 'payout_failed',
            'ticket_reply', 'order_live', 'share_changed', 'subscription_past_due',
            'print_order_status',
        ];

        $key = in_array($type, $known, true) ? $type : 'unknown';

        $text = __('author.notifications.'.$key, [
            'title' => $data['title'] ?? '',
            'amount' => isset($data['total_bdt']) ? number_format((int) $data['total_bdt']) : '',
            'period' => $data['period'] ?? '',
            'ticket_no' => $data['ticket_no'] ?? '',
            'order_no' => $data['order_no'] ?? '',
            'status' => $data['status'] ?? '',
            'share' => $data['share_pct'] ?? '',
        ]);

        return ['text' => $text, 'url' => static::urlFor($type, $data)];
    }

    /** Deep link for an event type, or null when there is none. */
    protected static function urlFor(string $type, array $data): ?string
    {
        $locale = app()->getLocale();

        try {
            return match ($type) {
                'book_approved', 'book_rejected' => static::bookUrl($data, $locale),
                'application_approved', 'application_rejected' => route('author.dashboard', ['locale' => $locale]),
                'payout_approved', 'payout_released', 'payout_failed', 'share_changed' => route('author.earnings', ['locale' => $locale]),
                'ticket_reply' => isset($data['ticket_id'])
                    ? route('support.chat.show', ['locale' => $locale, 'ticket' => $data['ticket_id']])
                    : route('support.chat', ['locale' => $locale]),
                'order_live' => isset($data['order_id'])
                    ? route('orders.show', ['locale' => $locale, 'order' => $data['order_id']])
                    : route('orders.index', ['locale' => $locale]),
                'print_order_status' => route('print-orders.index', ['locale' => $locale]),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function bookUrl(array $data, string $locale): string
    {
        if (isset($data['book_id']) && Schema::hasTable('books')) {
            $book = Book::query()->find($data['book_id']);

            if ($book !== null && ! empty($book->slug)) {
                return route('books.show', ['locale' => $locale, 'slug' => $book->slug]);
            }
        }

        return route('author.books', ['locale' => $locale]);
    }
}
