<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Audited registry corrections; stock quantities still move only through the ledger. */
class StockRegistry
{
    public function __construct(private readonly StockLedger $ledger, private readonly StockReconciliation $counts) {}

    /** Save corrected identity and, if needed, submit a one-item count for independent review. */
    public function update(StockItem $item, array $data, User $actor): ?StockCount
    {
        $variant = StockLedger::normalizeLabel($data['variant'] ?? null);
        $batch = StockLedger::normalizeLabel($data['batch_code'] ?? null);
        $unit = StockLedger::normalizeLabel($data['unit'] ?? null);
        if ($unit === null) throw ValidationException::withMessages(['unit' => 'Enter a stock unit.']);
        $identity = StockLedger::identityHash((int) $data['product_id'], $variant, $batch);
        $reason = $this->reason($data);
        try {
            $quantity = StockQuantity::fromInput($data['requested_quantity']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['requested_quantity' => $exception->getMessage()]);
        }

        try {
            return DB::transaction(function () use ($item, $data, $actor, $variant, $batch, $unit, $identity, $reason, $quantity) {
                // Match stock creation/product deletion: lock the target product before the item.
                $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
                $locked = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                $this->requireCurrent($locked, $data);
                if ($locked->archived_at) throw ValidationException::withMessages(['stock' => 'Restore the item before editing it.']);
                $this->ledger->assertConsistentLocked($locked);
                if (StockItem::where('identity_hash', $identity)->where('id', '<>', $locked->id)->exists()) {
                    throw ValidationException::withMessages(['batch_code' => 'This product, variant and batch already exist, including in the archive.']);
                }
                $expiry = $data['expires_on'] ?? null;
                $identityChanged = (int) $locked->product_id !== (int) $product->id || $locked->variant !== $variant
                    || $locked->batch_code !== $batch || $locked->expires_on?->format('Y-m-d') !== $expiry || $locked->unit !== $unit;
                $quantityChanged = $locked->quantity_milli !== $quantity;
                if (! $identityChanged && ! $quantityChanged) throw ValidationException::withMessages(['stock' => 'No stock details or quantity changed.']);

                $before = $this->snapshot($locked);
                if ($identityChanged) {
                    // Pre-migration rows did not carry identity snapshots. Capture
                    // their current identity before changing it; no quantities change.
                    $legacySnapshot = $locked->snapshot() + ['snapshot_basis' => 'legacy_pre_edit'];
                    DB::table('stock_movements')->where('stock_item_id', $locked->id)->whereNull('item_snapshot')
                        ->update(['item_snapshot' => json_encode($legacySnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
                if (! DB::table('stock_item_product_history')->where('stock_item_id', $locked->id)->where('product_id', $product->id)->exists()) {
                    // The product and item locks serialize assignment with both
                    // product deletion and other edits to this item's history.
                    DB::table('stock_item_product_history')->insert(['stock_item_id' => $locked->id, 'product_id' => $product->id]);
                }
                $locked->forceFill(['product_id' => $product->id, 'variant' => $variant, 'batch_code' => $batch,
                    'expires_on' => $expiry, 'unit' => $unit, 'identity_hash' => $identity,
                    'record_revision' => $locked->record_revision + 1,
                    'identity_revision' => $locked->identity_revision + ($identityChanged ? 1 : 0)])->save();
                $locked->setRelation('product', $product);

                $count = null;
                if ($quantityChanged) {
                    $count = $this->counts->create(['title' => 'Balance correction · '.$locked->number(),
                        'notes' => 'Requested from the stock register. Independent review is required.', 'item_ids' => [$locked->id]], $actor);
                    $line = $count->lines()->firstOrFail();
                    $this->counts->save($count, [$line->id => ['counted_qty' => StockQuantity::format($quantity), 'reason' => $reason]],
                        $actor, true, $count->revision);
                    $count->refresh();
                }
                $after = $this->snapshot($locked);
                if ($count) $after['requested_quantity'] = StockQuantity::format($quantity);
                $this->event($locked, 'updated', $actor, $reason, $before, $after, $count);
                return $count;
            }, 3);
        } catch (QueryException $exception) {
            // The unique identity remains reserved even for archived items.
            if (StockItem::where('identity_hash', $identity)->where('id', '<>', $item->id)->exists()) {
                throw ValidationException::withMessages(['batch_code' => 'This product, variant and batch already exist, including in the archive.']);
            }
            throw $exception;
        }
    }

    public function archive(StockItem $item, array $data, User $actor): void
    {
        $reason = $this->reason($data);
        DB::transaction(function () use ($item, $data, $actor, $reason) {
            $locked = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->requireCurrent($locked, $data);
            if ($locked->archived_at) throw ValidationException::withMessages(['stock' => 'This stock item is already archived.']);
            $this->ledger->assertConsistentLocked($locked);
            if ($locked->quantity_milli > 0 && ! in_array($data['acknowledge_nonzero'] ?? null, ['yes', 'on', '1', 1, true, 'true'], true)) {
                throw ValidationException::withMessages(['acknowledge_nonzero' => 'Acknowledge that this item still has stock before archiving it.']);
            }
            // Current locking read after the item lock; a newly-created count
            // must lock that same item, and count decisions lock their header.
            $open = DB::table('stock_count_lines as lines')->join('stock_counts as counts', 'counts.id', '=', 'lines.stock_count_id')
                ->where('lines.stock_item_id', $locked->id)->whereIn('counts.status', ['draft', 'submitted'])
                ->lockForUpdate()->first(['lines.id']);
            if ($open) throw ValidationException::withMessages(['stock' => 'Finish or cancel all open counts for this item before archiving it.']);
            $before = $this->snapshot($locked);
            $locked->forceFill(['archived_at' => now(), 'archived_by' => $actor->id,
                'record_revision' => $locked->record_revision + 1])->save();
            $this->event($locked, 'archived', $actor, $reason, $before, $this->snapshot($locked));
        }, 3);
    }

    public function restore(StockItem $item, array $data, User $actor): void
    {
        $reason = $this->reason($data);
        DB::transaction(function () use ($item, $data, $actor, $reason) {
            $locked = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->requireCurrent($locked, $data);
            if (! $locked->archived_at) throw ValidationException::withMessages(['stock' => 'This stock item is already active.']);
            $this->ledger->assertConsistentLocked($locked);
            $before = $this->snapshot($locked);
            $locked->forceFill(['archived_at' => null, 'archived_by' => null,
                'record_revision' => $locked->record_revision + 1])->save();
            $this->event($locked, 'restored', $actor, $reason, $before, $this->snapshot($locked));
        }, 3);
    }

    private function requireCurrent(StockItem $item, array $data): void
    {
        foreach (['record_revision', 'ledger_version'] as $key) {
            $value = $data[$key] ?? null;
            if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value)) {
                throw ValidationException::withMessages(['stock' => 'Reload the item before changing it.']);
            }
        }
        if ($item->record_revision !== (int) $data['record_revision'] || $item->ledger_version !== (int) $data['ledger_version']) {
            throw ValidationException::withMessages(['stock' => 'This item or its balance changed since you opened the form. Review the latest values before continuing.']);
        }
    }

    private function reason(array $data): string
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') throw ValidationException::withMessages(['reason' => 'Explain the change to this stock item.']);
        return $reason;
    }

    private function snapshot(StockItem $item): array
    {
        return $item->snapshot() + ['quantity_on_hand' => $item->quantity_on_hand, 'record_revision' => $item->record_revision,
            'archived' => $item->archived_at !== null];
    }

    private function event(StockItem $item, string $action, User $actor, string $reason, array $before, array $after, ?StockCount $count = null): void
    {
        $item->events()->create(['action' => $action, 'record_revision' => $item->record_revision, 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'reason' => $reason, 'before_snapshot' => $before, 'after_snapshot' => $after,
            'stock_count_id' => $count?->id]);
    }
}
