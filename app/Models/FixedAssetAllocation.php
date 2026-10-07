<?php

namespace App\Models;

use App\Services\AssetVocabulary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hand-over of an asset: who had it, where, from when, until when.
 *
 * The **open** row (no return date) is the asset's current holder, and it is the
 * only row that can be open — `AssetIntake::allocate()` closes the previous one
 * in the same transaction it opens the next, and the asset's own location,
 * department and custodian columns are written from it. This class adds no
 * writer: it is a record of something that happened, and the history is written
 * where the decision is made.
 */
class FixedAssetAllocation extends Model
{
    protected $table = 'fixed_asset_allocations';

    protected $fillable = [
        'fixed_asset_id', 'allocated_to', 'holder_name', 'location', 'department',
        'allocated_on', 'returned_on', 'condition_on_return', 'note', 'created_by',
    ];

    protected $casts = [
        'allocated_on' => 'date',
        'returned_on' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->returned_on === null;
    }

    /**
     * Who holds it: the person, or the room when it was never issued to one.
     *
     * The order matters — a linked person beats a typed name — because the link
     * is what the person's own record can be read back from.
     */
    public function holderLabel(): string
    {
        return $this->holder?->name ?: ($this->holder_name ?: 'Unassigned');
    }

    /** Where it was: "Ahmedabad office · Accounts", whichever halves exist. */
    public function placeLabel(): string
    {
        return trim(implode(' · ', array_filter([$this->location, $this->department]))) ?: '—';
    }

    /** "12 Mar 2025 → 04 Feb 2026", or "since 12 Mar 2025" while it is open. */
    public function periodLabel(): string
    {
        $from = $this->allocated_on?->format('d M Y') ?: '—';

        return $this->isOpen()
            ? 'since '.$from
            : $from.' → '.($this->returned_on?->format('d M Y') ?: '—');
    }

    /** How long it was (or has been) held, in days. Null while it is open-ended. */
    public function daysHeld(): ?int
    {
        if (! $this->allocated_on) {
            return null;
        }

        return $this->allocated_on->diffInDays($this->returned_on ?: now());
    }

    public function returnConditionLabel(): ?string
    {
        return $this->condition_on_return
            ? AssetVocabulary::conditionLabel($this->condition_on_return)
            : null;
    }
}
