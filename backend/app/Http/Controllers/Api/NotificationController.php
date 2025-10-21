<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Point 1: Get notifications for current user
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Notification::query()
            ->forUser($user->id)
            ->with('lead')
            ->orderByDesc('created_at');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by unread
        if ($request->filled('unread') && $request->unread === 'true') {
            $query->unread();
        }

        $notifications = $query->paginate($request->input('per_page', 20));

        return response()->json($notifications);
    }

    /**
     * Point 1: Get unread notification count
     * GET /api/notifications/unread-count
     */
    public function unreadCount()
    {
        $user = Auth::user();

        $count = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->count();

        return response()->json([
            'count' => $count
        ]);
    }

    /**
     * Point 1: Get unread WP lead notifications count
     * GET /api/notifications/wp-unread-count
     */
    public function wpUnreadCount()
    {
        $user = Auth::user();

        $count = Notification::query()
            ->forUser($user->id)
            ->wpLeads()
            ->unread()
            ->count();

        return response()->json([
            'count' => $count,
            'type' => 'wp_lead'
        ]);
    }

    /**
     * Point 1: Mark notification as read
     * POST /api/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = Notification::query()
            ->forUser($user->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    /**
     * Point 1: Mark all notifications as read
     * POST /api/notifications/mark-all-read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $updated = Notification::query()
            ->forUser($user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $updated
        ]);
    }

    /**
     * Point 1: Delete notification
     * DELETE /api/notifications/{id}
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = Notification::query()
            ->forUser($user->id)
            ->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Point 1: Clear all read notifications
     * POST /api/notifications/clear-read
     */
    public function clearRead()
    {
        $user = Auth::user();

        $deleted = Notification::query()
            ->forUser($user->id)
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Read notifications cleared',
            'count' => $deleted
        ]);
    }
}
