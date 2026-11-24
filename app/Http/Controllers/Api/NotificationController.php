<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** In-app + FCM history (spec §1.3). */
class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $rows = $request->user()->notifications()->latest()->paginate(20);

        return response()->json([
            'data' => $rows->getCollection()->map(function (DatabaseNotification $n) {
                $described = \App\Notifications\AuthorNotification::describe($n->data);

                return [
                    'id' => $n->id, 'type' => $n->data['type'] ?? null,
                    'text' => $described['text'], 'url' => $described['url'],
                    'data' => $n->data, 'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            })->values(),
            'links' => ['first' => $rows->url(1), 'last' => $rows->url($rows->lastPage()), 'prev' => $rows->previousPageUrl(), 'next' => $rows->nextPageUrl()],
            'meta' => ['locale' => app()->getLocale(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $row = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $row->markAsRead();

        return $this->ok(['read' => true]);
    }
}
