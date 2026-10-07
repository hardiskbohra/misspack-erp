<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetAllocation;
use App\Models\FixedAssetMaintenance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only thing that writes a fixed asset, its hand-overs or its repairs.
 *
 * Eight doors — write it, change it, hand it over, take it back, log a repair,
 * verify it, dispose of it, delete it — and every one of them is here. The
 * controllers validate and hand over facts; they never set `status`, never write
 * an allocation row and never decide whether an asset may be handed to somebody.
 * That matters here more than in most modules, because the register is the
 * company's *evidence*: an asset whose location was changed by a screen that
 * forgot to record who took it, or a disposal with no date, is a register that
 * stops being worth keeping.
 *
 * Four rules the class exists to hold:
 *
 *   - **the columns are the current answer, the rows are how it got there.**
 *     `location`, `department` and `custodian_id` are on the asset so the
 *     register can filter on them in one query; the allocation rows are the
 *     history. `allocate()` writes both in one transaction, and `update()` keeps
 *     the *open* row in step when the office corrects a location by hand — it
 *     never invents a hand-over, because a hand-over is a decision and a typo fix
 *     is not;
 *   - **a disposal is one act.** The status, the date, the money it fetched, the
 *     reason and the closing of the open hand-over land together, so there is
 *     never a moment in which an asset is "disposed" on paper and still in
 *     somebody's custody;
 *   - **the state follows the cupboard, and the office can still overrule it.**
 *     Handing an asset from the store to a person makes it *in use*; taking it
 *     back makes it *in store* again — the two moves that would otherwise leave
 *     the register telling a small lie. Anything else (under repair, disposed) is
 *     a decision, and decisions are made explicitly, including in the same click
 *     as the repair that prompted them;
 *   - **nothing is ever written about depreciation.** There is no accumulated
 *     figure to update here because there is none to store: the recipe is three
 *     columns, and the curve is `AssetDepreciation`'s.
 */
class AssetIntake
{
    public function __construct(private AssetDepreciation $depreciation)
    {
    }

    /* ------------------------------------------------------------------ the asset */

    /** A new asset, with its first hand-over if it arrived with a custodian. */
    public function create(User $author, array $facts): FixedAsset
    {
        $asset = new FixedAsset();

        $asset->status = $facts['status'] ?? AssetVocabulary::STATUS_IN_USE;
        $asset->created_by = $author->id;

        $this->fill($asset, $facts);
        $asset->save();

        /* Bought by Ravi, or already standing in the Ahmedabad office: that is a
           hand-over, and the history should begin with it rather than with the
           first *change* somebody makes. Dated on the purchase, because that is
           when it started being his. */
        if ($asset->location || $asset->department || $asset->custodian_id) {
            $this->open($asset, [
                'allocated_to' => $asset->custodian_id,
                'holder_name' => $facts['holder_name'] ?? null,
                'location' => $asset->location,
                'department' => $asset->department,
                'allocated_on' => $facts['allocated_on'] ?? $asset->purchase_date?->toDateString(),
                'note' => null,
            ], $author);
        }

        return $asset;
    }

    /**
     * Change an asset. The recipe, the purchase facts, the current place — and,
     * deliberately, not its history.
     *
     * An allocation row is written by `allocate()`, because a hand-over is a
     * decision with a date and a person attached. This method keeps the *open*
     * row in step with the columns instead, so correcting a misspelled location
     * does not create a fictitious hand-over — and the two views of "where is it"
     * still agree.
     */
    public function update(FixedAsset $asset, array $facts): FixedAsset
    {
        $this->fill($asset, $facts);

        if ($asset->isDirty()) {
            $asset->save();

            $open = $asset->allocations()->whereNull('returned_on')->latest('allocated_on')->first();

            if ($open) {
                $open->location = $asset->location;
                $open->department = $asset->department;
                $open->allocated_to = $asset->custodian_id;
                $open->save();
            }
        }

        return $asset;
    }

    /**
     * Hand it over: close the open row, open the next, and move the asset's own
     * columns with them — all in one transaction, because the invariant the whole
     * design rests on is "one open hand-over", and a half-written hand-over
     * breaks it.
     *
     * The state follows the cupboard only in the one direction that would
     * otherwise be a lie: an asset that was *in store* is now *in use*.
     */
    public function allocate(User $author, FixedAsset $asset, array $facts): FixedAssetAllocation
    {
        if ($asset->isDisposed()) {
            throw new RuntimeException('A disposed asset cannot be handed to anybody.');
        }

        $on = $facts['allocated_on'] ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($author, $asset, $facts, $on) {
            $this->close($asset, $on);

            if ($asset->status === AssetVocabulary::STATUS_SPARE) {
                $asset->status = AssetVocabulary::STATUS_IN_USE;
            }

            $asset->location = $this->text($facts['location'] ?? null);
            $asset->department = $this->text($facts['department'] ?? null);
            $asset->custodian_id = $facts['allocated_to'] ?? null;
            $asset->save();

            return $this->open($asset, array_merge($facts, ['allocated_on' => $on]), $author);
        });
    }

    /**
     * Take it back: the open hand-over closes, the asset has no holder, and an
     * asset that was *in use* goes back *in store*.
     *
     * Its location stays where it was — the store it came back to is usually the
     * same building, and clearing the field would lose the answer to "where is
     * it" rather than record the truth.
     */
    public function returnAsset(User $author, FixedAsset $asset, array $facts): ?FixedAssetAllocation
    {
        if ($asset->isDisposed()) {
            throw new RuntimeException('This asset was disposed of — there is nothing to take back.');
        }

        return DB::transaction(function () use ($asset, $facts) {
            $open = $asset->allocations()->whereNull('returned_on')->latest('allocated_on')->first();

            if (! $open) {
                throw new RuntimeException('Nothing is out — this asset has no open hand-over.');
            }

            $open->returned_on = $facts['returned_on'] ?? Carbon::today()->toDateString();
            $open->condition_on_return = AssetVocabulary::hasCondition($facts['condition'] ?? null)
                ? $facts['condition']
                : null;
            $open->save();

            $asset->custodian_id = null;
            $asset->location = $this->text($facts['location'] ?? null) ?: $asset->location;
            $asset->department = $this->text($facts['department'] ?? null) ?: $asset->department;

            if ($asset->status === AssetVocabulary::STATUS_IN_USE) {
                $asset->status = AssetVocabulary::STATUS_SPARE;
            }

            $asset->save();

            return $open;
        });
    }

    /**
     * A repair, a service, an AMC payment — and, in the same click, what it means
     * for the asset's state if the office says so.
     *
     * The state is **not** inferred from the log: a repair that came back on
     * Tuesday does not put the machine on the floor, and one that is still in the
     * workshop does not take it off unless somebody says. An optional status
     * arrives with the facts, and is applied only when it is one the module knows.
     */
    public function maintain(User $author, FixedAsset $asset, array $facts): FixedAssetMaintenance
    {
        return DB::transaction(function () use ($author, $asset, $facts) {
            $maintenance = new FixedAssetMaintenance();

            $maintenance->fixed_asset_id = $asset->id;
            $maintenance->created_by = $author->id;

            $this->fillMaintenance($maintenance, $facts);
            $maintenance->save();

            if (AssetVocabulary::hasStatus($facts['status'] ?? null) && $facts['status'] !== $asset->status) {
                /* A disposed asset is not un-disposed by a repair log. */
                if (! $asset->isDisposed()) {
                    $asset->status = $facts['status'];
                    $asset->save();
                }
            }

            return $maintenance;
        });
    }

    /**
     * The physical verification: who looked, when, and what they found.
     *
     * This is the CARO question in one click — "proper records … and a physical
     * verification at reasonable intervals" — so the three facts are written
     * together by one door, and the register's attention count is the assets
     * nobody has done this to in a year.
     */
    public function verify(User $author, FixedAsset $asset, array $facts): FixedAsset
    {
        if ($asset->isDisposed()) {
            throw new RuntimeException('This asset is disposed — a physical verification is for what we still hold.');
        }

        $asset->last_verified_on = $facts['last_verified_on'] ?? Carbon::today()->toDateString();
        $asset->last_verified_by = $author->id;

        if (AssetVocabulary::hasCondition($facts['condition'] ?? null)) {
            $asset->condition = $facts['condition'];
        }

        $asset->save();

        return $asset;
    }

    /**
     * Off the books: the state, the date, what it fetched, the reason and the end
     * of its custody, as one act.
     *
     * From the day after the disposal date the asset's net book value reads zero
     * — the balance sheet does not hold it — and what it fetched is measured
     * against the value it had on the day it left. Nothing about it is deleted:
     * the register keeps what it was, what it cost and what happened.
     */
    public function dispose(User $author, FixedAsset $asset, array $facts): FixedAsset
    {
        if ($asset->isDisposed()) {
            throw new RuntimeException('This asset has already been disposed of.');
        }

        $on = $facts['disposal_date'] ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($asset, $facts, $on) {
            $this->close($asset, $on);

            $asset->status = AssetVocabulary::STATUS_DISPOSED;
            $asset->disposal_date = $on;
            $asset->disposal_value = isset($facts['disposal_value']) && $facts['disposal_value'] !== ''
                ? round((float) $facts['disposal_value'], 2)
                : null;
            $asset->custodian_id = null;

            /* The reason joins the remarks rather than replacing them: the notes
               somebody wrote about the asset in 2024 are still true. */
            $reason = $this->text($facts['reason'] ?? null);
            if ($reason) {
                $line = 'Disposed '.Carbon::parse($on)->format('d M Y').' — '.$reason;
                $asset->remarks = trim(($asset->remarks ? $asset->remarks."\n\n" : '').$line);
            }

            $asset->save();

            return $asset;
        });
    }

    /**
     * Delete the row. Allowed for any asset, in any state, because it is the
     * office's own register — and the confirmation on both doors says exactly what
     * goes with it: the allocation history and the maintenance history cascade
     * (they are the asset's own story), and the register's figures move because a
     * row left.
     *
     * Nothing else in the application points at an asset: a fixed asset is not a
     * ledger entry and not a document, so there is no money to un-book — the
     * purchase it came from was recorded wherever it was recorded.
     */
    public function delete(FixedAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $asset->allocations()->delete();
            $asset->maintenances()->delete();
            $asset->delete();
        });
    }

    /**
     * The next asset ID in the office's own series — `FA-2026-014`.
     *
     * The ID is theirs: it is what their register, their stickers and the
     * auditor's schedule use, so the form suggests the next one and lets it be
     * overwritten. A suggestion is not a lock, which is also why the column is
     * unique and a clash is a validation message rather than an overwrite.
     */
    public function suggestCode(): string
    {
        $prefix = 'FA-'.date('Y').'-';

        $last = FixedAsset::query()
            ->where('asset_code', 'like', $prefix.'%')
            ->orderByDesc('asset_code')
            ->value('asset_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /* ------------------------------------------------------------------ internals */

    /** Close the open hand-over, if there is one, on the date the next thing happened. */
    private function close(FixedAsset $asset, string $on): void
    {
        $open = $asset->allocations()->whereNull('returned_on')->latest('allocated_on')->first();

        if (! $open) {
            return;
        }

        /* A hand-over that opens on the same day it closes is not a hand-over;
           returning and re-issuing in one day is a correction, not history. */
        $open->returned_on = $open->allocated_on?->greaterThan(Carbon::parse($on)) ? $open->allocated_on : $on;
        $open->save();
    }

    /** Open the next hand-over. */
    private function open(FixedAsset $asset, array $facts, User $author): FixedAssetAllocation
    {
        $allocation = new FixedAssetAllocation();

        $allocation->fixed_asset_id = $asset->id;
        $allocation->created_by = $author->id;
        $allocation->allocated_to = $facts['allocated_to'] ?? null;
        $allocation->holder_name = $this->text($facts['holder_name'] ?? null);
        $allocation->location = $this->text($facts['location'] ?? null);
        $allocation->department = $this->text($facts['department'] ?? null);
        $allocation->allocated_on = $facts['allocated_on'] ?? Carbon::today()->toDateString();
        $allocation->note = $this->text($facts['note'] ?? null);
        $allocation->save();

        return $allocation;
    }

    /**
     * The one place an asset's fields are set from facts.
     *
     * `array_key_exists` rather than `??`: a field the form sent as empty is a
     * field the person cleared, and "cleared" and "not mentioned" are different
     * things. The three recipe fields are the exception in the other direction —
     * an empty recipe field means **inherit from the class**, which is a real
     * answer and not a hole.
     */
    private function fill(FixedAsset $asset, array $facts): void
    {
        foreach ([
            'asset_code' => fn ($value) => trim((string) $value),
            'name' => fn ($value) => trim((string) $value),
            'category_id' => fn ($value) => $value ?: null,
            'description' => fn ($value) => $this->text($value),
            'make' => fn ($value) => $this->text($value),
            'model' => fn ($value) => $this->text($value),
            'serial_no' => fn ($value) => $this->text($value),
            'purchase_date' => fn ($value) => $value,
            'vendor_id' => fn ($value) => $value ?: null,
            'supplier_name' => fn ($value) => $this->text($value),
            'invoice_no' => fn ($value) => $this->text($value),
            'invoice_date' => fn ($value) => $value ?: null,
            'cost' => fn ($value) => round((float) $value, 2),
            'gst_amount' => fn ($value) => round((float) ($value ?: 0), 2),
            'depreciate_on_total' => fn ($value) => (bool) $value,
            'location' => fn ($value) => $this->text($value),
            'department' => fn ($value) => $this->text($value),
            'custodian_id' => fn ($value) => $value ?: null,
            'status' => fn ($value) => AssetVocabulary::hasStatus($value) ? $value : AssetVocabulary::STATUS_IN_USE,
            'warranty_end_date' => fn ($value) => $value ?: null,
            'useful_life_years' => fn ($value) => ($value === null || $value === '') ? null : max(1, (int) $value),
            'depreciation_method' => fn ($value) => AssetVocabulary::hasMethod($value) ? $value : null,
            'residual_percent' => fn ($value) => ($value === null || $value === '') ? null : max(0, min(100, round((float) $value, 2))),
            'insurance_details' => fn ($value) => $this->text($value),
            'insurance_expiry' => fn ($value) => $value ?: null,
            'remarks' => fn ($value) => $this->text($value),
        ] as $field => $cast) {
            if (array_key_exists($field, $facts)) {
                $asset->{$field} = $cast($facts[$field]);
            }
        }
    }

    /** The maintenance row's own fields — the same `array_key_exists` rule. */
    private function fillMaintenance(FixedAssetMaintenance $maintenance, array $facts): void
    {
        foreach ([
            'kind' => fn ($value) => AssetVocabulary::hasKind($value) ? $value : AssetVocabulary::KIND_SERVICE,
            'performed_on' => fn ($value) => $value,
            'vendor_id' => fn ($value) => $value ?: null,
            'vendor_name' => fn ($value) => $this->text($value),
            'invoice_no' => fn ($value) => $this->text($value),
            'cost' => fn ($value) => round((float) ($value ?: 0), 2),
            'downtime_from' => fn ($value) => $value ?: null,
            'downtime_to' => fn ($value) => $value ?: null,
            'next_due_on' => fn ($value) => $value ?: null,
            'notes' => fn ($value) => $this->text($value),
        ] as $field => $cast) {
            if (array_key_exists($field, $facts)) {
                $maintenance->{$field} = $cast($facts[$field]);
            }
        }
    }

    /** An empty string is no value, not a value made of spaces. */
    private function text($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
