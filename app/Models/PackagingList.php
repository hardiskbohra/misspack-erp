<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PackagingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'packing_number', 'packing_date', 'invoice_number', 'transport_mode', 'status', 'source_type', 'source_id',
        'vendor_id', 'client_id', 'project_id', 'shipment_id', 'sales_invoice_id', 'purchase_invoice_id',
        'from_name', 'from_address', 'from_contact', 'from_email',
        'bill_to_name', 'bill_to_address', 'ship_to_name', 'ship_to_address',
        'declaration', 'customer_signature', 'supplier_signature', 'notes',
        'total_cartons', 'total_net_weight', 'total_gross_weight', 'total_cbm', 'created_by',
        'packing_owner_id', 'auto_generated', 'packing_warnings', 'print_columns', 'print_settings',
    ];

    protected $casts = [
        'packing_date' => 'date',
        'total_net_weight' => 'decimal:3',
        'total_gross_weight' => 'decimal:3',
        'total_cbm' => 'decimal:4',
        'auto_generated' => 'boolean',
        'packing_warnings' => 'array',
        'print_columns' => 'array',
        'print_settings' => 'array',
    ];

    public function printSettings(): array
    {
        $settings = $this->print_settings ?? [];
        return [
            'orientation' => in_array($settings['orientation'] ?? '', ['portrait', 'landscape'], true) ? $settings['orientation'] : 'portrait',
            'header_text' => (string) ($settings['header_text'] ?? ''),
            'footer_text' => (string) ($settings['footer_text'] ?? 'This is a computer-generated packaging list. The cartons listed above are packed as stated.'),
            'show_page_numbers' => (bool) ($settings['show_page_numbers'] ?? false),
        ];
    }

    /** Canonical print order; saved selections never change packing calculations. */
    public static function printColumnOptions(): array
    {
        return [
            'serial' => 'Sr. no.', 'item' => 'Item description', 'cartons' => 'Carton',
            'qty' => 'Qty / carton', 'total_qty' => 'Total qty', 'size' => 'Carton size (cm)',
            'nw' => 'N.W. / carton', 'net' => 'Net weight', 'gw' => 'G.W. / carton',
            'gross' => 'Gross weight', 'cbm' => 'CBM/carton', 'total_cbm' => 'Total CBM',
        ];
    }

    public static function defaultPrintColumns(): array
    {
        // Preserve the existing eleven-column layout for old and newly created lists.
        return array_values(array_diff(array_keys(static::printColumnOptions()), ['cbm']));
    }

    public function selectedPrintColumns(): array
    {
        $selected = array_values(array_intersect(array_keys(static::printColumnOptions()), $this->print_columns ?? static::defaultPrintColumns()));
        return $selected ?: static::defaultPrintColumns();
    }

    public function printColumnWidths(): array
    {
        $weights = ['serial' => 4.5, 'item' => 21.5, 'cartons' => 5.5, 'qty' => 7.5,
            'total_qty' => 8, 'size' => 14, 'nw' => 7.5, 'net' => 7.5, 'gw' => 7.5,
            'gross' => 7.5, 'cbm' => 9, 'total_cbm' => 9];
        $weights = array_intersect_key($weights, array_flip($this->selectedPrintColumns()));
        $total = array_sum($weights);
        return array_map(fn ($weight) => $weight / $total * 100, $weights);
    }

    public function cartons() { return $this->hasMany(PackagingListCarton::class)->orderBy('sort_order')->orderBy('id'); }
    public function verifications() { return $this->hasMany(PackagingListVerification::class)->latest('verified_at')->latest('id'); }
    public function latestVerification() { return $this->hasOne(PackagingListVerification::class)->latestOfMany('verified_at'); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function client() { return $this->belongsTo(Client::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function salesInvoice() { return $this->belongsTo(SalesInvoice::class); }
    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function packingOwner() { return $this->belongsTo(User::class, 'packing_owner_id'); }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            'sales_invoice' => 'Sales invoice',
            'purchase_invoice' => 'Purchase invoice',
            'shipment' => 'Shipment',
            default => 'Manual',
        };
    }

    public function statusLabel(): string { return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status); }
    public function modeLabel(): string { return self::transportModeOptions()[$this->transport_mode] ?? Str::headline((string) $this->transport_mode); }

    public static function statusOptions(): array
    {
        return ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'dispatched' => 'Dispatched', 'cancelled' => 'Cancelled'];
    }

    public static function transportModeOptions(): array
    {
        return ['air' => 'Air', 'sea' => 'Sea', 'road' => 'Road', 'rail' => 'Rail', 'courier' => 'Courier'];
    }

    /**
     * Is this sheet issued by us, or by the vendor who packed the goods?
     *
     * A packaging list documents a packing run, and the party who packed issues
     * it: our own export leaves "MissPack India Pvt Ltd" in the From block and
     * is printed on our letterhead; a list drawn from a vendor's bill (or typed
     * with the vendor's name) belongs to that vendor's letterhead and must not
     * carry our logo.
     *
     * The test is the sender name against the organisation's own names — the
     * same strings a sales invoice puts there (`seller_company_name`). Legal
     * boilerplate differs between screens ("Private Limited" here, "Pvt Ltd"
     * there), so a name matches when one contains the other, and failing that
     * when the first word does. With no sender typed at all, a list with a
     * vendor is the vendor's and a list without one is ours.
     */
    public function issuedByUs(?Organisation $organisation = null): bool
    {
        $sender = self::normaliseName($this->from_name);

        if ($sender === '') {
            return ! $this->vendor_id;
        }

        $organisation ??= Organisation::current();
        $names = array_filter([
            self::normaliseName($organisation->legal_name),
            self::normaliseName($organisation->trade_name),
        ]);

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
            if (str_contains(" {$sender} ", " {$name} ") || str_contains(" {$name} ", " {$sender} ")) {
                return true;
            }
        }

        foreach ($names as $name) {
            $first = explode(' ', $name)[0];
            if (mb_strlen($first) >= 3 && str_contains(" {$sender} ", " {$first} ")) {
                return true;
            }
        }

        return false;
    }

    /** A name reduced to the words that identify it: "MissPack India Pvt. Ltd." → "misspack india pvt ltd". */
    private static function normaliseName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    public function totalChargeableWeight(): float
    {
        $cartons = $this->relationLoaded('cartons') ? $this->cartons : $this->cartons()->get();
        return round((float) $cartons->sum(fn ($carton) => $carton->totalChargeableWeight()), 3);
    }

    /**
     * The list as sections — the way the printed sheet is read.
     *
     * A packaging list is stored as carton blocks, because a carton is what has
     * a size and a weight. But the sheet that comes back from the supplier is
     * not a list of cartons: it is a set of headings — "50ml Empty Spray
     * Bottle", "Silicone Ring Case" — and under each heading the carton blocks
     * packed for it. Two different products can sit under one heading, and one
     * block can hold two products in the same carton (a bottle and its hook).
     *
     * The heading is `packing_group`, typed on the block. Blocks sharing a
     * heading form one section, in the order they were entered. A block with no
     * heading is its own section, titled by its carton label and then by its
     * first item — so an existing list reads exactly as it printed before.
     *
     * Derived on read: there is no section table to keep in step.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function sections(): Collection
    {
        $cartons = $this->relationLoaded('cartons') ? $this->cartons : $this->cartons()->with('items')->get();
        /* A caller that loaded only the cartons must not cost one query per
           block here; a relation that is already set is left alone. */
        $cartons->each(fn (PackagingListCarton $carton) => $carton->loadMissing('items'));
        $sections = [];

        foreach ($cartons as $carton) {
            $heading = trim((string) $carton->packing_group);
            $key = $heading !== '' ? 'group:'.$heading : 'block:'.$carton->id;

            if (! isset($sections[$key])) {
                $sections[$key] = [
                    'key' => $key,
                    'label' => $heading !== '' ? $heading : $this->blockLabel($carton),
                    'cartons' => [],
                    'cartons_count' => 0,
                    'quantity' => 0.0,
                    'net' => 0.0,
                    'gross' => 0.0,
                    'cbm' => 0.0,
                    'chargeable' => 0.0,
                ];
            }

            $sections[$key]['cartons'][] = $carton;
            $sections[$key]['cartons_count'] += (int) $carton->carton_count;
            $sections[$key]['quantity'] += (float) $carton->items->sum(fn ($item) => (float) $item->total_quantity);
            $sections[$key]['net'] += $carton->totalNetWeight();
            $sections[$key]['gross'] += $carton->totalGrossWeight();
            $sections[$key]['cbm'] += $carton->totalCbm();
            $sections[$key]['chargeable'] += $carton->totalChargeableWeight();
        }

        return collect($sections)->map(function (array $section) {
            $section['net'] = round($section['net'], 3);
            $section['gross'] = round($section['gross'], 3);
            $section['cbm'] = round($section['cbm'], 4);
            $section['chargeable'] = round($section['chargeable'], 3);

            return $section;
        })->values();
    }

    /** What a block with no heading is called: its label, then its first item. */
    private function blockLabel(PackagingListCarton $carton): string
    {
        $label = trim((string) $carton->carton_label);

        if ($label !== '') {
            return $label;
        }

        $first = trim((string) $carton->items->first()?->item_description);

        if ($first !== '') {
            return $first;
        }

        $number = trim((string) $carton->carton_number);

        return $number !== '' ? 'Carton '.$number : 'Unnamed packing group';
    }

    /** Every packed unit on the list, whichever carton it sits in. */
    public function totalQuantity(): float
    {
        $cartons = $this->relationLoaded('cartons') ? $this->cartons : $this->cartons()->with('items')->get();
        $cartons->each(fn (PackagingListCarton $carton) => $carton->loadMissing('items'));

        return round((float) $cartons->flatMap->items->sum(fn ($item) => (float) $item->total_quantity), 3);
    }

    public function recalculate(): void
    {
        $cartons = $this->cartons()->get();
        $this->forceFill([
            'total_cartons' => (int) $cartons->sum('carton_count'),
            'total_net_weight' => round((float) $cartons->sum(fn ($row) => $row->totalNetWeight()), 3),
            'total_gross_weight' => round((float) $cartons->sum(fn ($row) => $row->totalGrossWeight()), 3),
            'total_cbm' => round((float) $cartons->sum(fn ($row) => $row->totalCbm()), 4),
        ])->saveQuietly();
    }
}
