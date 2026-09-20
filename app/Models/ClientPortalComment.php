<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPortalComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'client_portal_user_id', 'related_type', 'related_id', 'author_type',
        'internal_user_id', 'body', 'is_public_to_client', 'is_read_by_internal',
    ];

    protected $casts = [
        'is_public_to_client' => 'boolean',
        'is_read_by_internal' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function internalUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'internal_user_id');
    }

    public function authorName(): string
    {
        if ($this->author_type === 'internal' && $this->internalUser) {
            return $this->internalUser->name;
        }

        if ($this->portalUser) {
            return $this->portalUser->displayName();
        }

        return $this->author_type === 'internal' ? 'Internal Team' : 'Client';
    }
}
