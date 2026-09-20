<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProjectProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'product_id', 'product_name', 'sku', 'product_snapshot', 'quantity', 'unit',
        'unit_price', 'total_amount', 'currency', 'status', 'stage', 'assignee_id', 'vendor_id',
        'vendor_invoice_number', 'expected_ready_date', 'actual_ready_date', 'notes', 'sort_order',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'expected_ready_date' => 'date',
        'actual_ready_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProjectProduct $projectProduct) {
            $qty = (float) ($projectProduct->quantity ?: 0);
            $price = (float) ($projectProduct->unit_price ?: 0);
            $projectProduct->total_amount = round($qty * $price, 2);
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class)->latest('id');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class)->latest('id');
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function publicMilestones()
    {
        return $this->hasMany(ProjectMilestone::class)->where('is_public', true)->orderBy('sort_order')->orderBy('id');
    }
    
    public function currentMilestone()
    {
        return $this->milestones
            ->whereNotIn('status', ['completed', 'skipped'])
            ->sortBy('sort_order')
            ->first();
    }

    public function trackingUpdates()
    {
        return $this->hasMany(ProjectTrackingUpdate::class)->latest('occurred_at')->latest('id');
    }

    public function logs()
    {
        return $this->hasMany(ProjectLog::class)->latest('id');
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function stageLabel(): string
    {
        return self::stageOptions()[$this->stage] ?? Str::headline((string) $this->stage);
    }

    public static function statusOptions(): array
    {
        return [
            'planned' => 'Planned',
            'sourcing' => 'Sourcing',
            'po_pending' => 'PO Pending',
            'artwork_pending' => 'Artwork Pending',
            'artwork_review' => 'Artwork Review',
            'pps_development' => 'PPS Development',
            'pps_review' => 'PPS Review',
            'in_production' => 'In Production',
            'qc_pending' => 'QC Pending',
            'ready' => 'Ready',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function stageOptions(): array
    {
        return [
            'pending' => 'Pending',
            'vendor_invoice' => 'Vendor Invoice',
            'artwork' => 'Artwork',
            'sampling' => 'Sampling',
            'production' => 'Production',
            'packaging_list' => 'Packaging List',
            'quality_check' => 'Quality Check',
            'dispatch' => 'Dispatch',
            'done' => 'Done',
        ];
    }
}
