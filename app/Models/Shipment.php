<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shipment extends Model
{
    use HasFactory;

    public const TYPE_DOMESTIC = 'domestic';
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';

    public const STATUS_PLANNING = 'planning';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_CUSTOM_HOLD = 'custom_hold';
    public const STATUS_DELAYED = 'delayed';
    public const STATUS_OUT_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shipment_number', 'identity_name', 'shipment_type', 'shipment_mode', 'pickup_date', 'drop_date', 'shipment_label',
        'from_name', 'from_address', 'from_city', 'from_state', 'from_country', 'from_pincode', 'from_email', 'from_mobile',
        'to_name', 'to_address', 'to_city', 'to_state', 'to_country', 'to_pincode', 'to_email', 'to_mobile',
        'logistic_partner', 'tracking_number', 'bill_of_entry_number', 'origin_port', 'destination_port',
        'status', 'shipment_cost', 'currency', 'cost_borne_by', 'package_count', 'gross_weight', 'chargeable_weight',
        'notes', 'public_token', 'created_by', 'client_id', 'show_client_portal', 'project_id', 'vendor_id',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'drop_date' => 'date',
        'shipment_cost' => 'decimal:2',
        'gross_weight' => 'decimal:3',
        'chargeable_weight' => 'decimal:3',
        'show_client_portal' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment) {
            if (! $shipment->public_token) {
                $shipment->public_token = Str::random(48);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function histories()
    {
        return $this->hasMany(ShipmentTrackingHistory::class)->latest('event_time')->latest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('identity_name', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('bill_of_entry_number', 'like', "%{$search}%")
                    ->orWhere('logistic_partner', 'like', "%{$search}%")
                    ->orWhere('from_name', 'like', "%{$search}%")
                    ->orWhere('to_name', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline($this->status);
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->shipment_type] ?? Str::headline($this->shipment_type);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PLANNING => 'Planning',
            self::STATUS_PICKED_UP => 'Picked up',
            self::STATUS_IN_TRANSIT => 'In transit',
            self::STATUS_CUSTOM_HOLD => 'On custom hold',
            self::STATUS_DELAYED => 'Delayed',
            self::STATUS_OUT_DELIVERY => 'Out for delivery',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_DOMESTIC => 'Domestic',
            self::TYPE_IMPORT => 'Import',
            self::TYPE_EXPORT => 'Export',
        ];
    }
    
    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }
    
    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
    }
    
    public function project()
    {
        return $this->belongsTo(\App\Models\Project::class, 'project_id');
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'RMB' => 'RMB', 'USD' => 'USD'];
    }

    public static function costBorneByOptions(): array
    {
        return ['shipper' => 'Shipper', 'receiver' => 'Receiver', 'misspack' => 'MissPack'];
    }

    public static function modeOptions(): array
    {
        return ['courier' => 'Courier', 'air' => 'Air', 'sea' => 'Sea', 'road' => 'Road', 'rail' => 'Rail'];
    }
    
    public function attachments()
    {
        return $this->hasMany(ShipmentAttachment::class)
            ->orderBy('sort_order')
            ->latest('id');
    }
    
    public function publicAttachments()
    {
        return $this->hasMany(ShipmentAttachment::class)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->latest('id');
    }
    
    public function labelColorClass()
    {
        if (!$this->shipment_label) {
            return 'label-0';
        }
    
        preg_match('/(\d+)$/', $this->shipment_label, $matches);
    
        $number = $matches[1] ?? 0;
    
        return 'label-' . ($number % 6);
    }
}
