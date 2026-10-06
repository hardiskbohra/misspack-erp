<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationContact extends Model
{
    public const DEPARTMENTS = [
        'office' => 'Office',
        'sales' => 'Sales',
        'operations' => 'Operations',
        'accounts' => 'Accounts',
        'purchase' => 'Purchase',
        'hr' => 'People',
        'support' => 'Support',
    ];

    protected $fillable = [
        'organisation_id', 'department', 'name', 'designation', 'email', 'mobile', 'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function departmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? ucfirst((string) $this->department);
    }
}
