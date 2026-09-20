<?php

namespace App\Services;

use App\Models\ClientPortalNotification;
use App\Models\ClientPortalUser;

class ClientPortalNotifier
{
    public function notifyClient(int $clientId, string $title, ?string $message = null, string $type = 'info', ?string $relatedType = null, ?int $relatedId = null, ?string $actionUrl = null): void
    {
        $users = ClientPortalUser::where('client_id', $clientId)
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty()) {
            ClientPortalNotification::create([
                'client_id' => $clientId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'action_url' => $actionUrl,
            ]);
            return;
        }

        foreach ($users as $user) {
            ClientPortalNotification::create([
                'client_id' => $clientId,
                'client_portal_user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'action_url' => $actionUrl,
            ]);
        }
    }

    public function notifyPortalUser(ClientPortalUser $portalUser, string $title, ?string $message = null, string $type = 'info', ?string $relatedType = null, ?int $relatedId = null, ?string $actionUrl = null): void
    {
        ClientPortalNotification::create([
            'client_id' => $portalUser->client_id,
            'client_portal_user_id' => $portalUser->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'action_url' => $actionUrl,
        ]);
    }
}
