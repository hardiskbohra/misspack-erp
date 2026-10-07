<?php

namespace App\Models;

use App\Services\AssetVocabulary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One maintenance or repair event on an asset.
 *
 * The row is what happened and what it cost — the asset's *state* is not here.
 * Logging a repair does not make the asset "under maintenance" and closing the
 * downtime does not make it "in use": those are decisions the office makes on
 * the asset, in one click, because a machine that came back from the workshop
 * on Tuesday is not automatically back on the floor.
 */
class FixedAssetMaintenance extends Model
{
    protected $table = 'fixed_asset_maintenances';

    protected $fillable = [
        'fixed_asset_id', 'kind', 'performed_on', 'vendor_id', 'vendor_name',
        'invoice_no', 'cost', 'downtime_from', 'downtime_to', 'next_due_on',
        'notes', 'created_by',
    ];

    protected $casts = [
        'performed_on' => 'date',
        'downtime_from' => 'date',
        'downtime_to' => 'date',
        'next_due_on' => 'date',
        'cost' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return AssetVocabulary::kindLabel($this->kind);
    }

    public function kindTone(): string
    {
        return AssetVocabulary::kindTone($this->kind);
    }

    /** The vendor as linked, or as typed — the same fallback the cashflow party uses. */
    public function vendorLabel(): ?string
    {
        return $this->vendor?->vendor_name ?: ($this->vendor_name ?: null);
    }

    /**
     * Days out of service — a reading of the two dates, never a number somebody
     * types (a stored "5 days down" is wrong the moment the workshop keeps it
     * another week). An open downtime counts up to today.
     */
    public function daysDown(): ?int
    {
        if (! $this->downtime_from) {
            return null;
        }

        return (int) $this->downtime_from->diffInDays($this->downtime_to ?: Carbon::today());
    }

    public function downtimeLabel(): ?string
    {
        if (! $this->downtime_from) {
            return null;
        }

        $days = $this->daysDown();
        $range = $this->downtime_from->format('d M').' → '.($this->downtime_to?->format('d M') ?: 'still down');

        return $range.($days !== null ? ' · '.$days.' '.\Illuminate\Support\Str::plural('day', $days) : '');
    }

    public function isDueSoon(int $days = 60): bool
    {
        if (! $this->next_due_on) {
            return false;
        }

        return $this->next_due_on->isFuture() && $this->next_due_on->diffInDays(now()) <= $days;
    }

    public function isOverdue(): bool
    {
        return $this->next_due_on !== null && $this->next_due_on->isPast();
    }
}
