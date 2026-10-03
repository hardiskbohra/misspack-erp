<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientPortalConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'client_portal_user_id', 'subject', 'category', 'priority', 'status',
        'last_message_at', 'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function messages()
    {
        return $this->hasMany(ClientPortalMessage::class, 'conversation_id')->orderBy('id');
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeUnreadByStaff(Builder $query): Builder
    {
        return $query->whereHas('messages', fn (Builder $messages) => $messages
            ->where('sender_type', 'client')
            ->whereNull('read_at'));
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? Str::headline((string) $this->category);
    }

    public function priorityLabel(): string
    {
        return self::priorityOptions()[$this->priority] ?? Str::headline((string) $this->priority);
    }

    public static function statusOptions(): array
    {
        return [
            'open' => 'Open',
            'waiting' => 'Waiting for you',
            'resolved' => 'Resolved',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'general' => 'General question',
            'project' => 'Project',
            'shipment' => 'Shipment',
            'invoice' => 'Invoice or billing',
            'payment' => 'Payment',
            'account' => 'Account access',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            'normal' => 'Normal',
            'high' => 'High',
        ];
    }
}
