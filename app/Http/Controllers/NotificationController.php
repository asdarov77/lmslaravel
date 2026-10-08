<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Уведомления пользователя.
 *
 * Колокольчик в шапке: список непрочитанных, отметка прочитанным.
 */
class NotificationController extends Controller
{
    /**
     * Список уведомлений текущего пользователя.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'link' => $n->link,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toISOString(),
            ]);

        $unread = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return response()->json([
            'data' => [
                'items' => $notifications,
                'unread' => $unread,
            ],
        ]);
    }

    /**
     * Отметить уведомление прочитанным.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        $notification->markAsRead();

        return response()->json(['success' => true, 'data' => null]);
    }

    /**
     * Отметить все прочитанными.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'data' => null]);
    }
}
