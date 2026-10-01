<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Collection;

/**
 * Remembers the From / To parties used on previous shipments.
 *
 * A shipper like "MissPack" is almost always the same address, phone and
 * email every time. Instead of retyping it (and typo-ing it), the form asks
 * this directory for the last known details of the name being typed.
 *
 * Design notes
 * ------------
 * - Values are read per column from the newest shipment whose copy of that
 *   column is not empty, so a half-filled newer shipment never blanks out a
 *   detail we already knew.
 * - Names are matched case-insensitively and trimmed, so "misspack " and
 *   "MissPack" resolve to the same party.
 * - The service is the single place that knows how parties are looked up, so
 *   the shipment form, the quick-shipment modal and any future import tool
 *   all prefill the same way.
 */
class ShipmentPartyDirectory
{
    public const PARTIES = ['from', 'to'];

    /** Columns that belong to a party block, in form order. */
    public const DETAIL_COLUMNS = ['email', 'mobile', 'address', 'city', 'state', 'country', 'pincode'];

    public function normaliseField(?string $field): string
    {
        return in_array($field, self::PARTIES, true) ? $field : 'from';
    }

    /**
     * Distinct party names already used on shipments, for the form datalist.
     */
    public function names(string $field, int $limit = 200): Collection
    {
        $column = $this->column($field, 'name');

        return Shipment::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->distinct()
            ->limit($limit)
            ->pluck($column)
            ->values();
    }

    /**
     * The best known details for a party name.
     *
     * @return array{name: string, details: array<string, string>, source: array{id: int, shipment_number: ?string}|null}
     */
    public function lookup(string $field, ?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return ['name' => '', 'details' => [], 'source' => null];
        }

        $nameColumn = $this->column($field, 'name');

        // A fresh builder per column: the newest shipment that actually has a
        // value for that column wins, so a half-filled newer record cannot
        // blank out a detail we already knew.
        $matches = fn () => Shipment::query()
            ->whereRaw('LOWER(TRIM('.$nameColumn.')) = ?', [mb_strtolower($name)]);

        $source = $matches()->orderByDesc('id')->first(['id', 'shipment_number']);

        if (! $source) {
            return ['name' => $name, 'details' => [], 'source' => null];
        }

        $details = [];

        foreach (self::DETAIL_COLUMNS as $suffix) {
            $column = $this->column($field, $suffix);

            $value = $matches()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderByDesc('id')
                ->value($column);

            if ($value !== null && $value !== '') {
                $details[$suffix] = (string) $value;
            }
        }

        return [
            'name' => $name,
            'details' => $details,
            'source' => [
                'id' => (int) $source->id,
                'shipment_number' => $source->shipment_number,
            ],
        ];
    }

    /**
     * Payload for the form prefill endpoint — details plus the suggestion list
     * so one request fills both the datalist and the block.
     */
    public function payload(string $field, ?string $name): array
    {
        $field = $this->normaliseField($field);

        return array_merge(
            ['field' => $field],
            $this->lookup($field, $name),
            ['names' => $this->names($field)]
        );
    }

    private function column(string $field, string $suffix): string
    {
        return $this->normaliseField($field).'_'.$suffix;
    }
}
