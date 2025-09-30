<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use App\Http\Resources\PostResource;

class NotificationController extends Controller
{
    public function makeNotification($userId, $title, $body)
    {
        $notification = Notification::createNotification($userId, $title, $body);
        if ($notification) {
            return new PostResource(true, 'Notification created successfully', $notification);
        }
        return new PostResource(false, 'Failed to create notification', null);
    }

    // function for get lot of notifications for a user
    public function getUnreadCountNotifications(Request $request)
    {
        $user = $request->user();

        $unreadNotifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return new PostResource(true, 'Unread notifications count retrieved', [
            'unread_notifications' => $unreadNotifications
        ]);
    }

    // Get all notifications for the authenticated user
    public function indexNotifications(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return new PostResource(true, 'Notifications retrieved', [
            'notifications' => $notifications
        ]);
    }

    // Mark a notification as read
    public function markAsReadNotification(Request $request, $notificationId)
    {
        $user = $request->user();

        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return new PostResource(false, 'Notification not found', null);
        }

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = Carbon::now();
            $notification->save();
        }

        return new PostResource(true, 'Notification marked as read', $notification);
    }
}
