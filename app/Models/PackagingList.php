<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
