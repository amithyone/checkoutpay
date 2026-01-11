<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Show notifications
     */
    public function index(Request $request)
    {
        $business = auth('business')->user();
        
        $query = $business->notifications()->latest();

        // Filter by read status
        if ($request->has('filter') && $request->filter === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(30);

        return view('business.notifications.index', compact('notifications'));
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(BusinessNotification $notification)
    {
        $business = auth('business')->user();

        if ($notification->business_id !== $business->id) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $business = auth('business')->user();
        
        $business->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    /**
     * Get unread notifications count (API endpoint)
     */
    public function unreadCount()
    {
        $business = auth('business')->user();
        $count = $business->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }
}
