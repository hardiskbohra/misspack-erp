<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'department',
        'designation',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /** First two initials from name */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = strtoupper(substr($words[0], 0, 1));
        if (isset($words[1])) {
            $initials .= strtoupper(substr($words[1], 0, 1));
        }
        return $initials;
    }

    /** Full URL to avatar or null */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    public function assignedTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'assignee_id');
    }

    public function createdTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'created_by');
    }

    public function createdShipments()
    {
        return $this->hasMany(\App\Models\Shipment::class, 'created_by');
    }

    public function shipmentTrackingUpdates()
    {
        return $this->hasMany(\App\Models\ShipmentTrackingHistory::class, 'created_by');
    }

    public function createdClients()
    {
        return $this->hasMany(\App\Models\Client::class, 'created_by');
    }

    public function reviewedClientKycs()
    {
        return $this->hasMany(\App\Models\Client::class, 'kyc_reviewed_by');
    }

    public function createdVendors()
    {
        return $this->hasMany(\App\Models\Vendor::class, 'created_by');
    }

    public function cashflowEntries()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'created_by');
    }

    public function assignedLeads()
    {
        return $this->hasMany(\App\Models\Lead::class, 'assigned_to');
    }

    public function createdLeads()
    {
        return $this->hasMany(\App\Models\Lead::class, 'created_by');
    }

    public function createdVendorQuotes()
    {
        return $this->hasMany(\App\Models\VendorQuote::class, 'created_by');
    }
}
