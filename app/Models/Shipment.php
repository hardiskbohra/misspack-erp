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

    /** How many days before expiry an e-way bill starts shouting. */
    public const EWAY_WARNING_DAYS = 3;

    protected $fillable = [
        'shipment_number', 'identity_name', 'shipment_type', 'shipment_mode', 'pickup_date', 'drop_date', 'shipment_label',
        'from_name', 'from_address', 'from_city', 'from_state', 'from_country', 'from_pincode', 'from_email', 'from_mobile',
        'to_name', 'to_address', 'to_city', 'to_state', 'to_country', 'to_pincode', 'to_email', 'to_mobile',
        'logistic_partner', 'tracking_number', 'bill_of_entry_number', 'origin_port', 'destination_port',
        'status', 'shipment_cost', 'currency', 'cost_borne_by', 'package_count', 'gross_weight', 'chargeable_weight',
        'notes', 'public_token', 'created_by', 'client_id', 'show_client_portal', 'project_id', 'vendor_id',
        'eta_date', 'delay_reason', 'sales_invoice_id', 'eway_bill_number', 'eway_bill_valid_until',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'drop_date' => 'date',
        'eta_date' => 'date',
        'eway_bill_valid_until' => 'date',
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

    /**
     * Where the shipment stands against its planned delivery date.
     *
     * overdue  — ETA has passed and the shipment is still open
     * due_soon — arriving within the next `$soonDays` days
     * on_track — open with an ETA further out
     * none     — closed, or no ETA captured yet
     */
    public function etaState(int $soonDays = 7): string
    {
        if ($this->isClosed() || ! $this->eta_date) {
            return 'none';
        }

        $days = $this->daysToEta();

        if ($days === null) {
            return 'none';
        }

        if ($days < 0) {
            return 'overdue';
        }

        return $days <= $soonDays ? 'due_soon' : 'on_track';
    }

    /**
     * Whole days until the ETA (negative = overdue), null when no ETA.
     */
    public function daysToEta(): ?int
    {
        if (! $this->eta_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->eta_date->startOfDay(), false);
    }

    public function etaLabel(): string
    {
        if (! $this->eta_date) {
            return 'No ETA';
        }

        $days = $this->daysToEta();

        if ($this->isClosed()) {
            return 'ETA '.$this->eta_date->format('d M Y');
        }

        if ($days === 0) {
            return 'Due today';
        }

        if ($days < 0) {
            return abs($days).' day'.(abs($days) === 1 ? '' : 's').' overdue';
        }

        return 'Due in '.$days.' day'.($days === 1 ? '' : 's');
    }

    /**
     * Filter used by the list's "needs attention" chips. Kept as a scope so
     * every screen that surfaces problem shipments agrees on the definition.
     */
    public function scopeAttention(Builder $query, ?string $type, int $staleDays = 7): Builder
    {
        return match ($type) {
            'overdue' => $query->open()
                ->whereNotNull('eta_date')
                ->whereDate('eta_date', '<', now()->toDateString()),
            'due_soon' => $query->open()
                ->whereNotNull('eta_date')
                ->whereDate('eta_date', '>=', now()->toDateString())
                ->whereDate('eta_date', '<=', now()->addDays(7)->toDateString()),
            'hold' => $query->open()->whereIn('status', [self::STATUS_CUSTOM_HOLD, self::STATUS_DELAYED]),
            'no_eta' => $query->open()->whereNull('eta_date'),
            'stale' => $query->open()->whereRaw(
                'COALESCE((select max(h.event_time) from shipment_tracking_histories h where h.shipment_id = shipments.id), shipments.created_at) < ?',
                [now()->subDays($staleDays)->toDateTimeString()]
            ),
            'docs_pending' => $query->whereDoesntHave('attachments', fn ($attachments) => $attachments->whereNotNull('document_type')),
            // Transport paper about to lapse (or already lapsed) on a shipment
            // that still has to move.
            'eway_expiring' => $query->open()
                ->whereNotNull('eway_bill_valid_until')
                ->whereDate('eway_bill_valid_until', '<=', now()->addDays(self::EWAY_WARNING_DAYS)->toDateString()),
            'needs_attention' => $query->open()->where(function (Builder $inner) use ($staleDays) {
                $inner->where(function (Builder $overdue) {
                    $overdue->whereNotNull('eta_date')->whereDate('eta_date', '<', now()->toDateString());
                })
                    ->orWhereIn('status', [self::STATUS_CUSTOM_HOLD, self::STATUS_DELAYED])
                    ->orWhereNull('eta_date')
                    ->orWhereRaw(
                        'COALESCE((select max(h.event_time) from shipment_tracking_histories h where h.shipment_id = shipments.id), shipments.created_at) < ?',
                        [now()->subDays($staleDays)->toDateTimeString()]
                    )
                    ->orWhere(function (Builder $eway) {
                        $eway->whereNotNull('eway_bill_valid_until')
                            ->whereDate('eway_bill_valid_until', '<=', now()->addDays(self::EWAY_WARNING_DAYS)->toDateString());
                    });
            }),
            default => $query,
        };
    }

    /**
     * Shipments that are still in motion (the opposite of closedStatuses()).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::closedStatuses());
    }

    /**
     * Statuses that mean the shipment is still moving. Everything else is
     * either finished (delivered / cancelled) or not yet dispatched.
     */
    public static function closedStatuses(): array
    {
        return [self::STATUS_DELIVERED, self::STATUS_CANCELLED];
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::closedStatuses(), true);
    }

    /** Today in the office's timezone — the app stores UTC, the office does not. */
    public static function businessToday(): string
    {
        return now(config('app.business_timezone', 'Asia/Kolkata'))->toDateString();
    }

    /**
     * A delivered shipment has a delivery date: the paperwork, the e-way bills
     * and every report downstream read it, so the status change is not allowed
     * to leave it empty. When nobody gave a date, the day of the change is the
     * honest answer.
     *
     * It only ever *fills* the date — an existing one, on the record or in the
     * submitted attributes, is never overwritten, so a delivery recorded late
     * keeps the date it was recorded with and correcting one stays an explicit
     * edit.
     *
     * @param  array<string, mixed>  $attributes  the attributes about to be saved
     * @return array<string, mixed>
     */
    public static function withDeliveryDefaults(array $attributes, ?self $shipment = null): array
    {
        if (($attributes['status'] ?? null) !== self::STATUS_DELIVERED) {
            return $attributes;
        }

        if (filled($attributes['drop_date'] ?? null) || filled($shipment?->drop_date)) {
            return $attributes;
        }

        $attributes['drop_date'] = static::businessToday();

        return $attributes;
    }

    /**
     * Operational list order: shipments still in motion come first, finished
     * ones (delivered / cancelled) drop below them; inside each group the
     * newest pickup date wins, then newest record.
     *
     * Kept as a scope so every shipment list (index, project tab, vendor tab)
     * can share the same ordering instead of each writing its own.
     */
    public function scopePriorityOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                'CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END',
                self::closedStatuses()
            )
            ->orderByRaw('pickup_date IS NULL')
            ->orderByDesc('pickup_date')
            ->orderByDesc('id');
    }

    /**
     * "INR 12,340.00" / "USD 2,400.00" — one place for money formatting so
     * lists, totals and the shipping mark always agree.
     */
    public static function formatAmount(?string $currency, $amount): string
    {
        return ($currency ?: 'INR').' '.number_format((float) $amount, 2);
    }

    /**
     * Turn [currency => total] into a compact display string,
     * e.g. "INR 1,20,000.00 · USD 2,400.00". Currency codes are never summed
     * together because rates differ per shipment.
     */
    public static function formatTotals(array $totals): string
    {
        $parts = [];

        foreach ($totals as $currency => $total) {
            if ((float) $total == 0.0) {
                continue;
            }

            $parts[] = self::formatAmount((string) $currency, $total);
        }

        return $parts ? implode(' · ', $parts) : '—';
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
    
    public function costs()
    {
        return $this->hasMany(ShipmentCost::class)->orderBy('sort_order')->orderBy('id');
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /**
     * Money summary used by the show page, the print pack and the portal:
     * per-currency cost heads, INR total, paid-so-far and margin against the
     * linked sales invoice.
     *
     * @return array{by_currency: array<string, float>, inr: float, paid_inr: float, rows: int, invoice_total: ?float, invoice_currency: ?string, margin: ?float, margin_percent: ?float}
     */
    public function costSummary(): array
    {
        $totals = app(\App\Services\ShipmentCostLedger::class)->totals($this);

        $invoice = $this->relationLoaded('salesInvoice') ? $this->salesInvoice : $this->salesInvoice()->first();
        $invoiceTotal = $invoice ? (float) $invoice->total_amount : null;
        $margin = $invoiceTotal !== null ? round($invoiceTotal - $totals['inr'], 2) : null;

        return $totals + [
            'invoice_total' => $invoiceTotal,
            'invoice_currency' => $invoice?->currency,
            'margin' => $margin,
            'margin_percent' => ($invoiceTotal && $margin !== null) ? round(($margin / $invoiceTotal) * 100, 1) : null,
        ];
    }

    /**
     * Consolidated freight per kilo — the number every logistics review asks
     * for. Uses chargeable weight when it was captured, otherwise gross.
     */
    public function costPerKg(): ?float
    {
        $weight = (float) ($this->chargeable_weight ?: $this->gross_weight);

        if ($weight <= 0) {
            return null;
        }

        $inr = app(\App\Services\ShipmentCostLedger::class)->totals($this)['inr'];

        return $inr > 0 ? round($inr / $weight, 2) : null;
    }

    /* ------------------------------------------------------------------
       Public tracking stages
       ------------------------------------------------------------------ */

    /**
     * The six stages every consignment walks through, in order. Shared by the
     * admin detail page, the client portal and the public tracker so the same
     * words appear everywhere.
     */
    public static function trackingStages(): array
    {
        return [
            self::STATUS_PLANNING => 'Booked',
            self::STATUS_PICKED_UP => 'Picked up',
            self::STATUS_IN_TRANSIT => 'In transit',
            self::STATUS_CUSTOM_HOLD => 'Customs clearance',
            self::STATUS_OUT_DELIVERY => 'Out for delivery',
            self::STATUS_DELIVERED => 'Delivered',
        ];
    }

    /**
     * 0-based index of the stage the shipment currently sits on, or null when
     * a stepper makes no sense (cancelled).
     *
     * "Delayed" is deliberately not a stage of its own: a delay happens either
     * in transit or while customs is holding the consignment, so it maps onto
     * whichever of those the tracking history proves.
     */
    public function trackingStage(): ?int
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return null;
        }

        $stages = array_keys(self::trackingStages());

        if ($this->status === self::STATUS_DELAYED) {
            $heldAtCustoms = $this->relationLoaded('histories')
                && $this->histories->contains(fn ($history) => $history->status === self::STATUS_CUSTOM_HOLD);

            $index = array_search($heldAtCustoms ? self::STATUS_CUSTOM_HOLD : self::STATUS_IN_TRANSIT, $stages, true);

            return $index === false ? null : $index;
        }

        $index = array_search($this->status, $stages, true);

        return $index === false ? null : $index;
    }

    public function trackingStageLabel(): ?string
    {
        $index = $this->trackingStage();

        return $index === null ? null : (array_values(self::trackingStages())[$index] ?? null);
    }

    /* ------------------------------------------------------------------
       E-way bill validity
       ------------------------------------------------------------------ */

    public function ewayState(int $soonDays = self::EWAY_WARNING_DAYS): string
    {
        if (! $this->eway_bill_valid_until) {
            return 'none';
        }

        $days = (int) now()->startOfDay()->diffInDays($this->eway_bill_valid_until->startOfDay(), false);

        if ($days < 0) {
            return 'expired';
        }

        return $days <= $soonDays ? 'expiring' : 'valid';
    }

    public function ewayLabel(): string
    {
        if (! $this->eway_bill_valid_until) {
            return $this->eway_bill_number ? 'No validity recorded' : 'No e-way bill';
        }

        $days = (int) now()->startOfDay()->diffInDays($this->eway_bill_valid_until->startOfDay(), false);

        if ($days < 0) {
            return 'Expired '.abs($days).' day'.(abs($days) === 1 ? '' : 's').' ago';
        }

        if ($days === 0) {
            return 'Valid till today';
        }

        return 'Valid '.$days.' more day'.($days === 1 ? '' : 's');
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
