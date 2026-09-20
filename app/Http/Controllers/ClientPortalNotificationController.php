<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalNotificationController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $portalUser = $this->portalUser($request);
        $notifications = ClientPortalNotification::where('client_id', $portalUser->client_id)
            ->where(function ($query) use ($portalUser) {
                $query->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $portalUser->id);
            })
            ->latest('id')
            ->paginate(20);

        return view('client_portal.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, ClientPortalNotification $notification): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        abort_unless($notification->client_id === $portalUser->client_id && (! $notification->client_portal_user_id || $notification->client_portal_user_id === $portalUser->id), 404);

        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $portalUser = $this->portalUser($request);
        ClientPortalNotification::where('client_id', $portalUser->client_id)
            ->where(function ($query) use ($portalUser) {
                $query->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $portalUser->id);
            })
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
