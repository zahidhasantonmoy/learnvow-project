<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (spec §6) — in-app notifications only (database channel).
 * The notifications table is Builder C's migration; until it lands,
 * the list renders empty instead of erroring.
 */
class NotificationController extends Controller
{
    use BuildsSeo;

    public function index(string $locale)
    {
        return view('notifications.index', [
            'seo' => $this->seo(__('author.notifications_title'), null, ['noindex' => true]),
        ]);
    }

    public function markRead(string $locale, string $notification, Request $request)
    {
        if (Schema::hasTable('notifications')) {
            $note = $request->user()->notifications()->where('id', $notification)->first();
            $note?->markAsRead();
        }

        return back();
    }

    /** Notification preferences (profile/notifications.blade.php). */
    public function settings(string $locale)
    {
        return view('profile.notifications', [
            'seo' => $this->seo(__('author.notification_settings_title'), null, ['noindex' => true]),
        ]);
    }

    public function updateSettings(string $locale, Request $request)
    {
        $data = $request->validate([
            'payouts' => 'nullable|boolean',
            'approvals' => 'nullable|boolean',
            'tickets' => 'nullable|boolean',
            'announcements' => 'nullable|boolean',
        ]);

        if (Schema::hasTable('notification_settings')) {
            \DB::table('notification_settings')->updateOrInsert(
                ['user_id' => $request->user()->id],
                [
                    'payouts' => $request->boolean('payouts', true),
                    'approvals' => $request->boolean('approvals', true),
                    'tickets' => $request->boolean('tickets', true),
                    'announcements' => $request->boolean('announcements', true),
                ]
            );
        }

        return back()->with('status', __('author.notification_settings_saved'));
    }
}
