<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPortalMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ClientPortalConversation::class, 'conversation_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(ClientPortalUser::class, 'sender_id');
    }

    public function staffUser()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function senderName(): string
    {
        if ($this->sender_type === 'staff') {
            return $this->staffUser?->name ?: 'MissPack Team';
        }

        return $this->portalUser?->displayName() ?: 'Client Team';
    }
}
