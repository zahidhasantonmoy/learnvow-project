<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Notification list (§6, §11) — in-app only.
 * The notifications table is Builder C's migration; until it lands,
 * the list renders empty instead of erroring.
 */
class NotificationList extends Component
{
    public function markRead(string $id): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        auth()->user()?->notifications()->where('id', $id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $notifications = collect();
        $unreadCount = 0;

        if (Schema::hasTable('notifications') && auth()->check()) {
            $notifications = auth()->user()->notifications()->latest()->limit(50)->get();
            $unreadCount = (int) auth()->user()->unreadNotifications()->count();
        }

        return view('livewire.notification-list', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
