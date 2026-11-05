<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Count snapshots are reviewed independently; only a current snapshot may post. */
class StockReconciliation
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function create(array $data, User $counter): StockCount
    {
        $ids = collect($data['item_ids'])->map(fn ($id) => (int) $id)->unique()->sort()->values();
        return DB::transaction(function () use ($data, $counter, $ids) {
            $items = StockItem::with('product')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($items->count() !== $ids->count()) throw ValidationException::withMessages(['item_ids' => 'One or more selected stock items no longer exist.']);
            foreach ($items as $item) {
                if ($item->archived_at) throw ValidationException::withMessages(['item_ids' => 'Restore archived items before starting a count.']);
                $this->ledger->assertConsistentLocked($item);
            }
            $count = StockCount::create(['title' => $data['title'], 'notes' => $data['notes'] ?? null,
                'status' => 'draft', 'revision' => 0, 'created_by' => $counter->id, 'counter_name' => $counter->name]);
            foreach ($items as $item) {
                $count->lines()->create(['stock_item_id' => $item->id, 'item_snapshot' => $item->snapshot(),
                    'book_milli' => $item->quantity_milli, 'ledger_version' => $item->ledger_version]);
            }
            $this->event($count, 'created', $counter, null, $this->snapshot($count->lines()->get()));
            return $count;
        }, 3);
    }

    public function save(StockCount $count, array $values, User $counter, bool $submit, int $revision): void
    {
        DB::transaction(function () use ($count, $values, $counter, $submit, $revision) {
            $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertCounterDraft($locked, $counter);
            $this->assertRevision($locked, $revision);
            $lines = $locked->lines()->lockForUpdate()->get();
            $allowed = $lines->keyBy('id');
            foreach ($values as $id => $row) {
                $line = $allowed->get((int) $id);
                if (! $line || ! is_array($row)) throw ValidationException::withMessages(['lines' => 'The count contains an unknown line.']);
                $raw = $row['counted_qty'] ?? null;
                $quantity = $raw === null || $raw === '' ? null : StockQuantity::fromInput($raw);
                $line->update(['counted_milli' => $quantity, 'reason' => trim((string) ($row['reason'] ?? '')) ?: null]);
            }
            if (! $submit) {
                $locked->update(['revision' => $locked->revision + 1]);
                return;
            }
            foreach ($lines as $line) {
                if ($line->counted_qty === null) throw ValidationException::withMessages(['lines' => 'Enter a physical quantity for every selected item. Blank does not mean zero.']);
                if ($line->variance() !== '0.000' && ! $line->reason) {
                    throw ValidationException::withMessages(['lines' => 'Explain every difference between physical and book quantities.']);
                }
            }
            $items = $this->lockItems($lines);
            $this->assertCurrent($lines, $items);
            $locked->update(['status' => 'submitted', 'submitted_at' => now(), 'revision' => $locked->revision + 1]);
            $this->event($locked, 'submitted', $counter, null, $this->snapshot($lines));
        }, 3);
    }

    public function approve(StockCount $count, User $reviewer, string $reason, int $revision): void
    {
        $this->requireReason($reason);
        DB::transaction(function () use ($count, $reviewer, $reason, $revision) {
            $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertIndependentReview($locked, $reviewer);
            $this->assertRevision($locked, $revision);
            $lines = $locked->lines()->lockForUpdate()->get();
            $items = $this->lockItems($lines);
            $this->assertCurrent($lines, $items);
            foreach ($lines as $line) {
                if ($line->counted_qty === null) throw ValidationException::withMessages(['lines' => 'This count has an incomplete line.']);
                $delta = $line->counted_milli - $line->book_milli;
                if ($delta === 0) continue;
                if (! $line->reason) throw ValidationException::withMessages(['lines' => 'Every variance needs a reason.']);
                $this->ledger->postLocked($items->get($line->stock_item_id), 'count_adjustment', $delta,
                    $line->reason, $locked->number(), 'count-line:'.$line->id, $reviewer, $line->id);
            }
            $locked->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $reviewer->id, 'approver_name' => $reviewer->name,
                'revision' => $locked->revision + 1]);
            $this->event($locked, 'posted', $reviewer, $reason, $this->snapshot($lines));
        }, 3);
    }

    public function reject(StockCount $count, User $reviewer, string $reason, int $revision): void
    {
        $this->requireReason($reason);
        DB::transaction(function () use ($count, $reviewer, $reason, $revision) {
            $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertIndependentReview($locked, $reviewer);
            $this->assertRevision($locked, $revision);
            $locked->update(['status' => 'draft', 'submitted_at' => null, 'revision' => $locked->revision + 1]);
            $this->event($locked, 'rejected', $reviewer, $reason, $this->snapshot($locked->lines()->get()));
        }, 3);
    }

    public function refresh(StockCount $count, User $counter, int $revision): void
    {
        DB::transaction(function () use ($count, $counter, $revision) {
            $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->assertCounterDraft($locked, $counter);
            $this->assertRevision($locked, $revision);
            $lines = $locked->lines()->lockForUpdate()->get();
            $items = $this->lockItems($lines);
            $before = $this->snapshot($lines);
            foreach ($lines as $line) {
                $item = $items->get($line->stock_item_id);
                $line->update(['book_milli' => $item->quantity_milli, 'ledger_version' => $item->ledger_version,
                    'counted_milli' => null, 'reason' => null, 'item_snapshot' => $item->snapshot()]);
            }
            $locked->update(['revision' => $locked->revision + 1]);
            $this->event($locked, 'refreshed', $counter, 'Book balances refreshed; previous physical counts cleared for recount.', $before);
        }, 3);
    }

    public function cancel(StockCount $count, User $actor, string $reason, int $revision): void
    {
        $this->requireReason($reason);
        DB::transaction(function () use ($count, $actor, $reason, $revision) {
            $locked = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['draft', 'submitted'], true)) {
                throw ValidationException::withMessages(['count' => 'Only an open count can be cancelled.']);
            }
            $this->assertRevision($locked, $revision);
            $locked->update(['status' => 'cancelled', 'revision' => $locked->revision + 1]);
            $this->event($locked, 'cancelled', $actor, $reason, $this->snapshot($locked->lines()->get()));
        }, 3);
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') throw ValidationException::withMessages(['reason' => 'A reason is required for this decision.']);
    }

    private function assertRevision(StockCount $count, int $revision): void
    {
        if ($count->revision !== $revision) {
            throw ValidationException::withMessages(['count' => 'This count changed since you opened it. Reload and review the latest values before continuing.']);
        }
    }

    private function assertCounterDraft(StockCount $count, User $actor): void
    {
        if ($count->status !== 'draft') throw ValidationException::withMessages(['count' => 'Only a draft count can be edited or refreshed.']);
        abort_unless($count->created_by && (int) $count->created_by === (int) $actor->id, 403, 'Only the assigned counter may change this count.');
    }

    private function assertIndependentReview(StockCount $count, User $actor): void
    {
        if ($count->status !== 'submitted') throw ValidationException::withMessages(['count' => 'This count is not awaiting approval.']);
        // A deleted counter cannot be verified as independent. Cancel and start a new count.
        abort_unless($count->created_by && (int) $count->created_by !== (int) $actor->id, 403, 'A different office user must review this count.');
    }

    /** Count header is locked first, then every item in stable ID order. */
    private function lockItems(Collection $lines): Collection
    {
        $items = StockItem::with('product')->whereIn('id', $lines->pluck('stock_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($items->count() !== $lines->count()) throw ValidationException::withMessages(['count' => 'A selected stock item is missing.']);
        foreach ($items as $item) {
            if ($item->archived_at) throw ValidationException::withMessages(['count' => 'A selected stock item is archived. Restore it before continuing this count.']);
            $this->ledger->assertConsistentLocked($item);
        }
        return $items;
    }

    private function assertCurrent(Collection $lines, Collection $items): void
    {
        $stale = $lines->filter(fn (StockCountLine $line) => $items->get($line->stock_item_id)->ledger_version !== $line->ledger_version
            || $items->get($line->stock_item_id)->quantity_milli !== $line->book_milli
            || $items->get($line->stock_item_id)->identity_revision !== (int) ($line->item_snapshot['identity_revision'] ?? 0));
        if ($stale->isNotEmpty()) {
            $names = $stale->map(fn ($line) => $line->item_snapshot['item_number'])->implode(', ');
            throw ValidationException::withMessages(['count' => 'Stock changed after the snapshot for '.$names.'. Reject or refresh the draft and recount before approval.']);
        }
    }

    private function snapshot(Collection $lines): array
    {
        return $lines->map(fn (StockCountLine $line) => ['stock_item_id' => $line->stock_item_id, 'item' => $line->item_snapshot,
            'book_qty' => $line->book_qty, 'ledger_version' => $line->ledger_version,
            'counted_qty' => $line->counted_qty, 'variance' => $line->variance(), 'reason' => $line->reason])->values()->all();
    }

    private function event(StockCount $count, string $action, User $actor, ?string $reason, ?array $snapshot): void
    {
        $count->events()->create(['action' => $action, 'count_revision' => $count->revision, 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'reason' => $reason, 'snapshot' => $snapshot]);
    }
}
