<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPortalNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'client_portal_user_id', 'type', 'title', 'message', 'related_type',
        'related_id', 'action_url', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }
}
