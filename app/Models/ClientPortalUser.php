<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class ClientPortalUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'name', 'username', 'email', 'mobile', 'password', 'portal_enabled',
        'is_active', 'must_change_password', 'password_changed_at', 'invitation_sent_at',
        'last_login_at', 'created_by', 'updated_by', 'remember_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'portal_enabled' => 'boolean',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'invitation_sent_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function notifications()
    {
        return $this->hasMany(ClientPortalNotification::class);
    }

    public function documents()
    {
        return $this->hasMany(ClientPortalDocument::class);
    }

    public function comments()
    {
        return $this->hasMany(ClientPortalComment::class);
    }

    public function setPassword(string $plainPassword): void
    {
        $this->password = Hash::make($plainPassword);
    }

    public function canLogin(): bool
    {
        return $this->portal_enabled && $this->is_active && $this->client && (bool) ($this->client->portal_enabled ?? true);
    }

    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->client) {
            return $this->client->company_name;
        }

        return $this->username;
    }
}
