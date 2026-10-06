<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationAddress extends Model
{
    public const KINDS = [
        'billing' => 'Billing',
        'shipping' => 'Shipping',
        'branch' => 'Branch',
    ];

    protected $fillable = [
        'organisation_id', 'kind', 'label', 'line1', 'line2',
        'city', 'state', 'country', 'pincode', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, $this->city, $this->state, $this->pincode, $this->country])
            ->filter()
            ->implode(', ');
    }
}
