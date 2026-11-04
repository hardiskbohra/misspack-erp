<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The only writer of stock balances and append-only ledger movements. */
class StockLedger
{
    public function createItem(array $data, User $actor): StockItem
    {
        $variant = self::clean($data['variant'] ?? null);
        $batch = self::clean($data['batch_code'] ?? null);
        $unit = self::clean($data['unit']);
        $identity = hash('sha256', json_encode([(int) $data['product_id'], self::key($variant), self::key($batch)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $quantity = StockQuantity::fromInput($data['opening_quantity']);

        try {
            return DB::transaction(function () use ($data, $actor, $variant, $batch, $unit, $identity, $quantity) {
                // Serializes with product deletion as well as other item creation for this product.
                $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
                if (StockItem::where('identity_hash', $identity)->exists()) {
                    throw ValidationException::withMessages(['batch_code' => 'This product, variant and batch already exist. Use the existing stock item.']);
                }
                $item = StockItem::create(['product_id' => $product->id, 'variant' => $variant, 'batch_code' => $batch,
                    'expires_on' => $data['expires_on'] ?? null, 'unit' => $unit, 'identity_hash' => $identity,
                    'quantity_milli' => 0, 'ledger_version' => 0,
                    'created_by' => $actor->id, 'creator_name' => $actor->name]);
                $this->postLocked($item, 'opening', $quantity, $data['reason'], $data['reference'] ?? null,
                    'opening:'.$item->id, $actor);
                return $item;
            }, 3);
        } catch (QueryException $error) {
            // A database unique key is the final defence even under competing requests.
            if (StockItem::where('identity_hash', $identity)->exists()) {
                throw ValidationException::withMessages(['batch_code' => 'This product, variant and batch already exist. Use the existing stock item.']);
            }
            throw $error;
        }
    }

    public function manualMovement(StockItem $item, array $data, User $actor): StockMovement
    {
        if (! in_array($data['type'] ?? null, ['receipt', 'issue'], true)) {
            throw ValidationException::withMessages(['type' => 'Choose a receipt or issue.']);
        }
        $quantity = StockQuantity::fromInput($data['quantity']);
        if ($quantity <= 0) throw ValidationException::withMessages(['quantity' => 'Enter a quantity greater than zero.']);
        $delta = $data['type'] === 'issue' ? -$quantity : $quantity;
        $key = 'manual:'.$data['request_key'];

        return DB::transaction(function () use ($item, $data, $actor, $delta, $key) {
            $locked = StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $existing = StockMovement::where('request_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->stock_item_id !== (int) $locked->id || (int) $existing->created_by !== (int) $actor->id
                    || $existing->type !== $data['type'] || $existing->delta_milli !== $delta
                    || $existing->reason !== $data['reason'] || ($existing->reference ?? '') !== ($data['reference'] ?? '')) {
                    throw ValidationException::withMessages(['quantity' => 'This request was already used for a different movement. Reload the page.']);
                }
                return $existing;
            }
            return $this->postLocked($locked, $data['type'], $delta, $data['reason'], $data['reference'] ?? null, $key, $actor);
        }, 3);
    }

    /** Caller must hold the stock item row lock inside the current transaction. */
    public function postLocked(StockItem $item, string $type, int $delta, string $reason, ?string $reference, string $key, User $actor, ?int $countLineId = null): StockMovement
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Stock postings require a transaction and a locked stock item.');
        $valid = match ($type) {
            'opening' => $item->ledger_version === 0 && $delta >= 0 && $countLineId === null,
            'receipt' => $delta > 0 && $countLineId === null,
            'issue' => $delta < 0 && $countLineId === null,
            'count_adjustment' => $delta !== 0 && $countLineId !== null,
            default => false,
        };
        if (! $valid || trim($reason) === '') throw new \LogicException('Invalid stock movement type, direction or reason.');
        $this->assertConsistentLocked($item, $type === 'opening');
        $next = $item->quantity_milli + $delta;
        if ($next < 0) throw ValidationException::withMessages(['quantity' => 'The issue exceeds the current stock balance.']);
        if ($next > StockQuantity::MAX_MILLI) throw ValidationException::withMessages(['quantity' => 'This movement exceeds the maximum supported balance.']);
        $version = $item->ledger_version + 1;
        $movement = StockMovement::create(['stock_item_id' => $item->id, 'type' => $type,
            'delta_milli' => $delta, 'balance_after_milli' => $next,
            'ledger_version' => $version, 'reason' => $reason, 'reference' => $reference, 'request_key' => $key,
            'stock_count_line_id' => $countLineId, 'created_by' => $actor->id, 'actor_name' => $actor->name]);
        $item->forceFill(['quantity_milli' => $next, 'ledger_version' => $version])->save();
        return $movement;
    }

    /** Verify the cached current balance agrees with the latest immutable ledger row. */
    public function assertConsistentLocked(StockItem $item, bool $allowNewItem = false): void
    {
        if (DB::transactionLevel() < 1) throw new \LogicException('Stock consistency checks require a locked item in a transaction.');
        $last = StockMovement::where('stock_item_id', $item->id)->orderByDesc('ledger_version')->lockForUpdate()->first();
        if (! $last && $allowNewItem && $item->ledger_version === 0 && $item->quantity_milli === 0) return;
        if (! $last || $last->ledger_version !== $item->ledger_version || $last->balance_after_milli !== $item->quantity_milli) {
            throw ValidationException::withMessages(['stock' => $item->number().' has an inconsistent cached balance. Contact an administrator before posting or counting.']);
        }
    }

    private static function clean(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        return $value === '' ? null : $value;
    }

    private static function key(?string $value): string { return mb_strtolower($value ?? '', 'UTF-8'); }
}
