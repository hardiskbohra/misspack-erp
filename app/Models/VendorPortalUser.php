<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class VendorPortalUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'name', 'username', 'email', 'mobile', 'password', 'portal_enabled',
        'is_active', 'must_change_password', 'password_changed_at', 'invitation_sent_at',
        'last_login_at', 'created_by', 'updated_by', 'remember_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'portal_enabled' => 'boolean', 'is_active' => 'boolean', 'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime', 'invitation_sent_at' => 'datetime', 'last_login_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function updates() { return $this->hasMany(VendorCollaborationUpdate::class); }

    public function setPassword(string $plain): void { $this->password = Hash::make($plain); }

    public function canLogin(): bool
    {
        return $this->portal_enabled && $this->is_active && $this->vendor
            && (bool) $this->vendor->portal_enabled
            && $this->vendor->status === Vendor::STATUS_ACTIVE;
    }

    public function displayName(): string
    {
        return $this->name ?: $this->vendor?->vendor_name ?: $this->username;
    }
}
