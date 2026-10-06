<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationBank extends Model
{
    protected $fillable = [
        'organisation_id', 'label', 'bank_name', 'account_holder',
        'account_number', 'ifsc', 'branch', 'swift', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
