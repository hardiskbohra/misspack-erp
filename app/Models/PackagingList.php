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
     * The packing lines grouped by the product they carry.
     *
     * One carton group is one packing configuration — a size, a weight per
     * carton, and how many cartons of it — and it may hold several items. But a
     * product is regularly packed in several configurations at once: 10 cartons
     * of 336, 2 cartons of 340 and 3 cartons of 300 are all the same 50ml spray
     * bottle, and that is what the receiving side reads: 15 cartons, 4,940
     * pieces, and one weight and CBM for the product.
     *
     * Read carton by carton that product appears three times and nothing adds
     * it up. This is that sum, derived from the rows already stored — no extra
     * column to keep in step.
     *
     * Carton-level figures (cartons, weight, CBM) are counted for a group only
     * when the carton belongs to that product alone. A carton shared by two
     * products has its row in both groups, marked `shared`, and contributes to
     * neither group's weight — splitting one carton's weight between products
     * would be a guess. `shared_cartons` tells the view to say so.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function productGroups(): Collection
    {
        $cartons = $this->relationLoaded('cartons') ? $this->cartons : $this->cartons()->with('items')->get();
        $groups = [];

        foreach ($cartons as $carton) {
            $items = $carton->items;
            $exclusive = $items->count() === 1;
            $count = (int) $carton->carton_count;

            foreach ($items as $item) {
                $key = $this->productKey($item);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'label' => $this->productLabel($item),
                        'sku' => trim((string) $item->sku),
                        'unit' => trim((string) $item->unit) ?: 'pcs',
                        'rows' => [],
                        'cartons' => 0,
                        'quantity' => 0.0,
                        'net' => 0.0,
                        'gross' => 0.0,
                        'cbm' => 0.0,
                        'chargeable' => 0.0,
                        'shared_cartons' => 0,
                    ];
                }

                $groups[$key]['rows'][] = [
                    'carton' => $carton,
                    'item' => $item,
                    'cartons' => $count,
                    'quantity_per_carton' => (float) $item->quantity_per_carton,
                    'quantity' => (float) $item->total_quantity,
                    'net_per_carton' => (float) $carton->net_weight_per_carton,
                    'gross_per_carton' => (float) $carton->gross_weight_per_carton,
                    'cbm_per_carton' => $carton->cbmPerCarton(),
                    'net' => $exclusive ? $carton->totalNetWeight() : 0.0,
                    'gross' => $exclusive ? $carton->totalGrossWeight() : 0.0,
                    'cbm' => $exclusive ? $carton->totalCbm() : 0.0,
                    'shared' => ! $exclusive,
                ];

                $groups[$key]['quantity'] += (float) $item->total_quantity;

                if ($exclusive) {
                    $groups[$key]['cartons'] += $count;
                    $groups[$key]['net'] += $carton->totalNetWeight();
                    $groups[$key]['gross'] += $carton->totalGrossWeight();
                    $groups[$key]['cbm'] += $carton->totalCbm();
                    $groups[$key]['chargeable'] += $carton->totalChargeableWeight();
                } else {
                    $groups[$key]['shared_cartons']++;
                }
            }
        }

        return collect($groups)->map(function (array $group) {
            $group['net'] = round($group['net'], 3);
            $group['gross'] = round($group['gross'], 3);
            $group['cbm'] = round($group['cbm'], 4);
            $group['chargeable'] = round($group['chargeable'], 3);

            return $group;
        })->values();
    }

    /** Every packed unit on the list, whichever carton it sits in. */
    public function totalQuantity(): float
    {
        $cartons = $this->relationLoaded('cartons') ? $this->cartons : $this->cartons()->with('items')->get();

        return round((float) $cartons->flatMap->items->sum(fn ($item) => (float) $item->total_quantity), 3);
    }

    /**
     * What makes two lines the same product: the link if there is one, the name
     * otherwise — and never across units, because 10 pieces and 10 kilograms
     * are not 20 of anything.
     */
    private function productKey(PackagingListItem $item): string
    {
        $unit = Str::lower(trim((string) $item->unit));

        if ($item->product_id) {
            return 'product:'.$item->product_id.'|'.$unit;
        }

        $description = (string) Str::of((string) $item->item_description)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish();

        return 'description:'.($description !== '' ? $description : 'line-'.$item->id).'|'.$unit;
    }

    private function productLabel(PackagingListItem $item): string
    {
        $description = trim((string) $item->item_description);

        if ($description !== '') {
            return $description;
        }

        $name = trim((string) $item->product?->name);

        return $name !== '' ? $name : 'Unnamed item';
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
