<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorShareLink extends Model
{
    use HasFactory;

    public const RESOURCE_TYPES = [
        'workspace' => 'Vendor workspace',
        'purchase_invoice' => 'Purchase document',
        'shipment' => 'Shipment',
        'packaging_list' => 'Packaging list',
    ];

    protected $fillable = [
        'vendor_id', 'token', 'label', 'resource_type', 'resource_id', 'permissions', 'expires_at',
        'revoked_at', 'revoked_by', 'views', 'first_viewed_at', 'last_viewed_at', 'last_viewed_ip', 'created_by',
    ];

    protected $casts = [
        'permissions' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime',
        'first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime', 'views' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $share) {
            if (! $share->token) {
                do { $share->token = Str::random(64); } while (self::where('token', $share->token)->exists());
            }
        });
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function revoker() { return $this->belongsTo(User::class, 'revoked_by'); }
    public function updates() { return $this->hasMany(VendorCollaborationUpdate::class); }

    public function isLive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function stateLabel(): string
    {
        if ($this->revoked_at) return 'Revoked';
        if ($this->expires_at?->isPast()) return 'Expired';
        return $this->views ? 'Opened' : 'Live';
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->permissions ?: [], true);
    }

    public function publicUrl(): string { return route('vendor-shares.show', $this->token); }

    public function recordView(?string $ip): void
    {
        $this->forceFill([
            'views' => $this->views + 1,
            'first_viewed_at' => $this->first_viewed_at ?: now(),
            'last_viewed_at' => now(),
            'last_viewed_ip' => $ip,
        ])->save();
    }
}
