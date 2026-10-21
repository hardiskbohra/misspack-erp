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
        'packing_owner_id', 'auto_generated', 'packing_warnings',
    ];

    protected $casts = [
        'packing_date' => 'date',
        'total_net_weight' => 'decimal:3',
        'total_gross_weight' => 'decimal:3',
        'total_cbm' => 'decimal:4',
        'auto_generated' => 'boolean',
        'packing_warnings' => 'array',
    ];

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
