<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_number', 'public_token', 'client_id', 'customer_quote_id', 'name', 'status',
        'stage', 'priority', 'health', 'start_date', 'target_date', 'completed_at', 'currency',
        'estimated_value', 'budget_amount', 'progress_percent', 'scope_summary', 'deliverables',
        'client_notes', 'internal_notes', 'show_client_portal', 'assigned_to', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_value' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'progress_percent' => 'integer',
        'show_client_portal' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (! $project->public_token) {
                $project->public_token = Str::random(48);
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    public function customerQuote()
    {
        return $this->belongsTo(\App\Models\CustomerQuote::class, 'customer_quote_id');
    }

    public function products()
    {
        return $this->hasMany(ProjectProduct::class)->orderBy('sort_order')->orderBy('id');
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class)->latest('id');
    }

    public function publicComments()
    {
        return $this->hasMany(ProjectComment::class)->where('is_public', true)->latest('id');
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class)->latest('id');
    }

    public function publicAttachments()
    {
        return $this->hasMany(ProjectAttachment::class)->where('is_public', true)->latest('id');
    }

    public function milestones()
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('project_product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function publicMilestones()
    {
        return $this->hasMany(ProjectMilestone::class)->where('is_public', true)->orderBy('project_product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function trackingUpdates()
    {
        return $this->hasMany(ProjectTrackingUpdate::class)->latest('occurred_at')->latest('id');
    }

    public function publicTrackingUpdates()
    {
        return $this->hasMany(ProjectTrackingUpdate::class)->where('is_public', true)->latest('occurred_at')->latest('id');
    }

    public function payments()
    {
        return $this->hasMany(ProjectPayment::class)->latest('payment_date')->latest('id');
    }

    public function cashflowEntries()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'project_id')->latest('entry_date')->latest('id');
    }

    public function shipments()
    {
        return $this->hasMany(\App\Models\Shipment::class, 'project_id')->latest('id');
    }

    public function publicPayments()
    {
        return $this->hasMany(ProjectPayment::class)->where('is_public', true)->latest('payment_date')->latest('id');
    }

    public function logs()
    {
        return $this->hasMany(ProjectLog::class)->latest('id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $nested) use ($search) {
                $nested->where('project_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('stage', 'like', "%{$search}%")
                    ->orWhere('scope_summary', 'like', "%{$search}%");
            });
        });
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function stageLabel(): string
    {
        return self::stageOptions()[$this->stage] ?? Str::headline((string) $this->stage);
    }

    public function priorityLabel(): string
    {
        return self::priorityOptions()[$this->priority] ?? Str::headline((string) $this->priority);
    }

    public function healthLabel(): string
    {
        return self::healthOptions()[$this->health] ?? Str::headline((string) $this->health);
    }

    public function clientName(): string
    {
        if ($this->relationLoaded('client') && $this->client) {
            return $this->client->company_name ?: ($this->client->brand_name ?: 'Client');
        }

        return 'Client #'.$this->client_id;
    }

    public function paymentTotals(): array
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();
    
        $cashflows = $this->relationLoaded('cashflows')
            ? $this->cashflows
            : $this->cashflowEntries()->get();
    
        // Payments
        $paymentInward = (float) $payments->where('transaction_type', 'inward')->sum('amount');
        $paymentOutward = (float) $payments->where('transaction_type', 'outward')->sum('amount');
    
        // Cashflows
        $cashflowCredit = (float) $cashflows->where('transaction_type', 'credit')->sum('credit_amount');
        $cashflowDebit = (float) $cashflows->where('transaction_type', 'debit')->sum('debit_amount');
    
        // Combined totals
        $inward = $paymentInward + $cashflowCredit;
        $outward = $paymentOutward + $cashflowDebit;
    
        $estimated = (float) ($this->estimated_value ?: 0);
    
        return [
            'inward' => $inward,
            'outward' => $outward,
            'net' => $inward - $outward,
            'outstanding' => max($estimated - $inward, 0),
        ];
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'planned' => 'Planned',
            'in_progress' => 'In Progress',
            'waiting_client' => 'Waiting for Client',
            'waiting_vendor' => 'Waiting for Vendor',
            'on_hold' => 'On Hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function stageOptions(): array
    {
        return [
            'quote_finalised' => 'Quote Finalised',
            'kickoff' => 'Kickoff',
            'sampling' => 'Sampling',
            'artwork_design' => 'Artwork / Design',
            'vendor_po' => 'Vendor PO',
            'client_pi' => 'Client PI',
            'pps' => 'PPS',
            'pps_qc' => 'PPS QC',
            'production' => 'Production',
            'quality_check' => 'Quality Check',
            'packing' => 'Packing',
            'dispatch_ready' => 'Dispatch Ready',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'closed' => 'Closed',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public static function healthOptions(): array
    {
        return [
            'green' => 'Green / On Track',
            'amber' => 'Amber / Attention',
            'red' => 'Red / Critical',
        ];
    }

    public static function currencyOptions(): array
    {
        return ['INR' => 'INR', 'USD' => 'USD', 'RMB' => 'RMB'];
    }
}
