<?php

namespace App\Models;

use App\Services\AssetDepreciation;
use App\Services\AssetVocabulary;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A fixed asset: one row of the company's register.
 *
 * Read the migration first — it explains the columns, the two that are
 * deliberately **not** columns (accumulated depreciation and net book value), and
 * why. This class is three things and nothing else:
 *
 *   - **relations** — its class, its vendor, who holds it, its two histories;
 *   - **readings** — what the recipe means on a given day. Every money figure
 *     below is derived, and every one of them is the *same* calculator:
 *     `AssetDepreciation`. Nothing re-implements the curve, because a second
 *     implementation of a depreciation schedule is a second answer to an
 *     auditor's question;
 *   - **scopes** — the queries the register asks: what is in use, what this
 *     category holds, whose cupboard it is in, what is out of warranty, what
 *     nobody has looked at this year.
 *
 * The two histories are **not** mirrored onto the asset: the maintenance spend is
 * read from the rows, and the days out of service with it. The only exception is
 * location / department / custodian, which *are* columns — the register has to
 * filter on "what is at the Ahmedabad office" without walking history — and
 * `AssetIntake::allocate()` is the one writer that keeps them and the open
 * allocation row in step.
 */
class FixedAsset extends Model
{
    protected $table = 'fixed_assets';

    protected $fillable = [
        'asset_code', 'name', 'category_id', 'description', 'make', 'model', 'serial_no',
        'purchase_date', 'vendor_id', 'supplier_name', 'invoice_no', 'invoice_date',
        'cost', 'gst_amount', 'depreciate_on_total',
        'location', 'department', 'custodian_id', 'status', 'warranty_end_date',
        'useful_life_years', 'depreciation_method', 'residual_percent',
        'insurance_details', 'insurance_expiry',
        'last_verified_on', 'last_verified_by', 'condition',
        'disposal_date', 'disposal_value', 'remarks', 'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'invoice_date' => 'date',
        'warranty_end_date' => 'date',
        'insurance_expiry' => 'date',
        'last_verified_on' => 'date',
        'disposal_date' => 'date',
        'cost' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'disposal_value' => 'decimal:2',
        'depreciate_on_total' => 'boolean',
        'useful_life_years' => 'integer',
        'residual_percent' => 'decimal:2',
    ];

    /* ---------------------------------------------------------------- relations */

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FixedAssetAllocation::class, 'fixed_asset_id');
    }

    /** The one open hand-over: who holds it now, and since when. */
    public function openAllocation(): HasOne
    {
        return $this->hasOne(FixedAssetAllocation::class, 'fixed_asset_id')
            ->whereNull('returned_on')
            ->latestOfMany('allocated_on');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(FixedAssetMaintenance::class, 'fixed_asset_id');
    }

    /** The next preventive service, if one is scheduled. */
    public function nextService(): HasOne
    {
        return $this->hasOne(FixedAssetMaintenance::class, 'fixed_asset_id')
            ->whereNotNull('next_due_on')
            ->orderBy('next_due_on');
    }

    /* ------------------------------------------------------- the depreciation */

    /** The module's one calculator. Every curve reading below is its. */
    private function depreciation(): AssetDepreciation
    {
        return app(AssetDepreciation::class);
    }

    public function schedule(): array
    {
        return $this->depreciation()->schedule($this);
    }

    public function chargeForYear(string $financialYear): float
    {
        return $this->depreciation()->chargeForYear($this, $financialYear);
    }

    public function accumulatedDepreciation(?CarbonInterface $on = null): float
    {
        return $this->depreciation()->accumulatedAt($this, $on);
    }

    public function netBookValue(?CarbonInterface $on = null): float
    {
        return $this->depreciation()->netBookValueAt($this, $on);
    }

    /** What it was worth on the day it was sold — the figure the profit is against. */
    public function bookValueOnDisposal(): ?float
    {
        if (! $this->disposal_date) {
            return null;
        }

        return $this->depreciation()->netBookValueAt($this, $this->disposal_date->copy()->subDay());
    }

    /**
     * Profit (positive) or loss (negative) on disposal, against book value.
     * Null while the asset is still on the books.
     */
    public function disposalGainLoss(): ?float
    {
        $book = $this->bookValueOnDisposal();

        if ($book === null) {
            return null;
        }

        return round((float) $this->disposal_value - $book, 2);
    }

    /* ------------------------------------------------------------ money facts */

    /** The invoice total: the cost plus the GST that was paid on it. Derived. */
    public function totalCost(): float
    {
        return round((float) $this->cost + (float) $this->gst_amount, 2);
    }

    /**
     * What depreciation runs on: the whole invoice when the credit was not
     * taken, the net value when it was. This is the figure the books carry.
     */
    public function capitalisedCost(): float
    {
        return $this->depreciate_on_total ? $this->totalCost() : round((float) $this->cost, 2);
    }

    /** Is the GST an input credit rather than part of the asset? */
    public function claimsInputCredit(): bool
    {
        return ! $this->depreciate_on_total && (float) $this->gst_amount > 0;
    }

    /** Total spent on keeping it running, across both histories. */
    public function maintenanceSpend(): float
    {
        if ($this->relationLoaded('maintenances')) {
            return round((float) $this->maintenances->sum('cost'), 2);
        }

        return round((float) $this->maintenances()->sum('cost'), 2);
    }

    /* ------------------------------------------------- the depreciation recipe */

    public function effectiveLifeYears(): ?int
    {
        return $this->useful_life_years ?: $this->category?->useful_life_years;
    }

    public function effectiveMethod(): string
    {
        return $this->depreciation_method
            ?: ($this->category?->depreciation_method ?: AssetVocabulary::METHOD_STRAIGHT_LINE);
    }

    public function effectiveResidualPercent(): float
    {
        $percent = $this->residual_percent ?? $this->category?->residual_percent;

        return round((float) ($percent ?? AssetVocabulary::DEFAULT_RESIDUAL_PERCENT), 2);
    }

    /** The residual value as the pages print it: a percentage, from the one formatter. */
    public function residualPercentLabel(): string
    {
        return AssetVocabulary::percentLabel($this->effectiveResidualPercent());
    }

    public function depreciates(): bool
    {
        return $this->effectiveMethod() !== AssetVocabulary::METHOD_NONE
            && (int) ($this->effectiveLifeYears() ?? 0) > 0
            && $this->purchase_date !== null;
    }

    /** Did this asset override its class, or follow it? The form says which. */
    public function inheritsRecipe(): bool
    {
        return $this->useful_life_years === null
            && $this->depreciation_method === null
            && $this->residual_percent === null;
    }

    public function methodLabel(): string
    {
        return AssetVocabulary::methodLabel($this->effectiveMethod());
    }

    public function recipeLabel(): string
    {
        if (! $this->depreciates()) {
            return 'Not depreciated';
        }

        return ($this->effectiveLifeYears().' '.\Illuminate\Support\Str::plural('year', $this->effectiveLifeYears()))
            .' · '.$this->methodLabel()
            .' · '.AssetVocabulary::percentLabelTrimmed($this->effectiveResidualPercent()).'% left';
    }

    /* ------------------------------------------------------- state and labels */

    public function isDisposed(): bool
    {
        return $this->status === AssetVocabulary::STATUS_DISPOSED;
    }

    public function stateLabel(): string
    {
        return AssetVocabulary::statusLabel($this->status);
    }

    public function stateTone(): string
    {
        return AssetVocabulary::statusTone($this->status);
    }

    public function conditionLabel(): string
    {
        return $this->condition ? AssetVocabulary::conditionLabel($this->condition) : 'Not recorded';
    }

    public function conditionTone(): string
    {
        return $this->condition ? AssetVocabulary::conditionTone($this->condition) : 'neutral';
    }

    /** Who holds it: the issued person, or the room it stands in. */
    public function holderLabel(): string
    {
        if ($this->custodian) {
            return $this->custodian->name;
        }

        return $this->openAllocation?->holderLabel() ?: 'Unassigned';
    }

    public function placeLabel(): string
    {
        return trim(implode(' · ', array_filter([$this->location, $this->department]))) ?: '—';
    }

    public function supplierLabel(): ?string
    {
        return $this->vendor?->vendor_name ?: ($this->supplier_name ?: null);
    }

    /** "2 years 4 months old", from the purchase date. */
    public function ageLabel(): ?string
    {
        if (! $this->purchase_date) {
            return null;
        }

        $months = (int) $this->purchase_date->diffInMonths(now());

        if ($months < 1) {
            return 'new this month';
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;

        return trim(($years ? $years.' '.\Illuminate\Support\Str::plural('year', $years) : '')
            .' '.($rest ? $rest.' '.\Illuminate\Support\Str::plural('month', $rest) : ''));
    }

    /* -------------------------------------------------- warranty and insurance */

    /** How long is left on the cover, in days — negative once it has run out. */
    public function warrantyDaysLeft(): ?int
    {
        return $this->warranty_end_date
            ? (int) now()->startOfDay()->diffInDays($this->warranty_end_date->startOfDay(), false)
            : null;
    }

    public function warrantyLabel(): string
    {
        $days = $this->warrantyDaysLeft();

        if ($days === null) {
            return 'Not recorded';
        }

        if ($days < 0) {
            return 'Expired '.$this->warranty_end_date->format('d M Y');
        }

        return 'Until '.$this->warranty_end_date->format('d M Y')
            .($days <= 60 ? ' · '.$days.' '.\Illuminate\Support\Str::plural('day', $days).' left' : '');
    }

    public function warrantyExpiring(int $days = 60): bool
    {
        $left = $this->warrantyDaysLeft();

        return $left !== null && $left >= 0 && $left <= $days;
    }

    public function warrantyExpired(): bool
    {
        $left = $this->warrantyDaysLeft();

        return $left !== null && $left < 0;
    }

    public function insuranceExpiring(int $days = 60): bool
    {
        if (! $this->insurance_expiry) {
            return false;
        }

        $left = (int) now()->startOfDay()->diffInDays($this->insurance_expiry->startOfDay(), false);

        return $left >= 0 && $left <= $days;
    }

    public function insuranceExpired(): bool
    {
        return $this->insurance_expiry !== null && $this->insurance_expiry->isPast();
    }

    /* ---------------------------------------------- physical verification (CARO) */

    /**
     * Nobody has laid eyes on it in a year — the interval an auditor's CARO
     * question is about, and the reading the register's attention stat uses.
     * A disposed asset is never "due for verification": it is not ours.
     */
    public function verificationOverdue(int $days = 365): bool
    {
        if ($this->isDisposed()) {
            return false;
        }

        if (! $this->last_verified_on) {
            return true;
        }

        return (int) $this->last_verified_on->diffInDays(now()) > $days;
    }

    public function verificationLabel(): string
    {
        if (! $this->last_verified_on) {
            return 'Never verified';
        }

        return 'Verified '.$this->last_verified_on->format('d M Y')
            .($this->verifier ? ' by '.$this->verifier->name : '');
    }

    /* ------------------------------------------------------------------ scopes */

    /**
     * The register's search: its own numbering, its name and serial, the invoice
     * it came on, where it stands — and the class and the person holding it,
     * because "the laptop with Ravi" is how people actually look for an asset.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_no', 'like', "%{$search}%")
                    ->orWhere('invoice_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('custodian', fn (Builder $person) => $person->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('vendor_name', 'like', "%{$search}%"));
            });
        });
    }

    public function scopeInStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->when($categoryId, fn (Builder $q) => $q->where('category_id', $categoryId));
    }

    public function scopeAtLocation(Builder $query, ?string $location): Builder
    {
        return $query->when($location, fn (Builder $q) => $q->where('location', $location));
    }

    public function scopeInDepartment(Builder $query, ?string $department): Builder
    {
        return $query->when($department, fn (Builder $q) => $q->where('department', $department));
    }

    public function scopeHeldBy(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, fn (Builder $q) => $q->where('custodian_id', $userId));
    }

    public function scopeWithCondition(Builder $query, ?string $condition): Builder
    {
        return $query->when($condition, fn (Builder $q) => $q->where('condition', $condition));
    }

    /** The warranty window: what is running out, what has run out, what was never recorded. */
    public function scopeWarrantyState(Builder $query, ?string $state): Builder
    {
        $today = Carbon::today();

        return match ($state) {
            'expiring' => $query->whereNotNull('warranty_end_date')
                ->whereDate('warranty_end_date', '>=', $today)
                ->whereDate('warranty_end_date', '<=', $today->copy()->addDays(60)),
            'expired' => $query->whereNotNull('warranty_end_date')->whereDate('warranty_end_date', '<', $today),
            'covered' => $query->whereNotNull('warranty_end_date')->whereDate('warranty_end_date', '>', $today->copy()->addDays(60)),
            'unrecorded' => $query->whereNull('warranty_end_date'),
            default => $query,
        };
    }

    /**
     * The maintenance diary: whatever is due inside the window — including what is
     * already overdue. One scope, so the register's "Service due" chip, the count
     * beside the link on the statistics row, and the repair log all ask the same
     * question of the same rows.
     */
    public function scopeServiceDue(Builder $query, ?int $days = null): Builder
    {
        if ($days === null) {
            return $query;
        }

        return $query->whereHas(
            'maintenances',
            fn (Builder $service) => $service
                ->whereNotNull('next_due_on')
                ->whereDate('next_due_on', '<=', Carbon::today()->copy()->addDays($days))
        );
    }

    /** The physical-verification window — the CARO reading, as a filter. */
    public function scopeVerificationState(Builder $query, ?string $state): Builder
    {
        $cutoff = Carbon::today()->subDays(365);

        return match ($state) {
            'overdue' => $query->where(function (Builder $q) use ($cutoff) {
                $q->whereNull('last_verified_on')->orWhereDate('last_verified_on', '<', $cutoff);
            })->where('status', '!=', AssetVocabulary::STATUS_DISPOSED),
            'never' => $query->whereNull('last_verified_on'),
            'recent' => $query->whereDate('last_verified_on', '>=', $cutoff),
            default => $query,
        };
    }

    /* The four orders the register can be read in — each one a scope, because
       the drawer's keys and these names are the same contract (see
       `AssetFilters::SORT_SCOPES`). There is deliberately no "net book value"
       order: that figure is a curve, not a column, and a sort that had to fetch
       every row to do its job is a sort that dies at a thousand assets. */

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('purchase_date')->orderByDesc('id');
    }

    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderBy('purchase_date')->orderBy('id');
    }

    public function scopeNameOrder(Builder $query): Builder
    {
        return $query->orderBy('name')->orderBy('id');
    }

    /** Largest invoice total first — the order, not the reading, so the sum runs in SQL. */
    public function scopeCostOrder(Builder $query): Builder
    {
        return $query->orderByRaw('(cost + gst_amount) desc')->orderByDesc('id');
    }
}
