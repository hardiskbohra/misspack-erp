<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_number', 'client_id', 'name', 'status',
        'stage', 'priority', 'health', 'start_date', 'target_date', 'completed_at', 'currency',
        'progress_percent', 'scope_summary', 'deliverables',
        'client_notes', 'internal_notes', 'show_client_portal', 'assigned_to', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'progress_percent' => 'integer',
        'show_client_portal' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
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

    /** Client-safe shared files; vendor invoices remain private even if misflagged. */
    public function clientPortalAttachments()
    {
        return $this->hasMany(ProjectAttachment::class)
            ->where('is_public', true)
            ->where(function ($query) {
                $query->whereNull('category')->orWhereNotIn('category', ['vendor_invoice']);
            })
            ->latest('id');
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

    public function cashflowEntries()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'project_id')->latest('entry_date')->latest('id');
    }

    public function shipments()
    {
        return $this->hasMany(\App\Models\Shipment::class, 'project_id')->latest('id');
    }

    /**
     * The receipts the client portal shows for this project. A project payment
     * used to be its own row; the cashflow ledger is the source of truth now,
     * so a receipt is a ledger entry tagged to the project — money in, and
     * booked or reconciled, so a tentative row never reaches a client. The
     * office's own ledger panel reads `cashflowEntries`, every status, on
     * purpose: the office may see what is not confirmed yet.
     */
    public function projectReceipts()
    {
        return $this->hasMany(\App\Models\CashflowEntry::class, 'project_id')
            ->moneyIn()
            ->whereIn('accounting_status', ['booked', 'reconciled'])
            ->latest('entry_date')
            ->latest('id');
    }

    /**
     * The money documents this project generated.
     *
     * A project does not raise an invoice here — the invoices module does, and
     * the row is tagged with `project_id` — so the record page reads the tag
     * instead of keeping a list of its own. The four relations are the four
     * documents the office asks for, each written once, in the order the
     * record's Invoices tab reads them: the tax invoices the client owes, the
     * proformas that asked for the money first, the purchase orders placed for
     * the job, and the bills the vendors raised against them. A proforma is
     * history once a tax invoice carries it; the section says so rather than
     * the relation hiding it.
     *
     * `payments` is eager-loaded by the reader so a page of documents costs one
     * query per relation instead of one per row: `receivedAmount()` and
     * `paidAmount()` both read the ledger rows through that relation.
     */
    public function taxInvoices()
    {
        return $this->hasMany(\App\Models\SalesInvoice::class, 'project_id')
            ->where('invoice_type', 'tax')
            ->latest('invoice_date')
            ->latest('id');
    }

    public function proformaInvoices()
    {
        return $this->hasMany(\App\Models\SalesInvoice::class, 'project_id')
            ->where('invoice_type', 'proforma')
            ->latest('invoice_date')
            ->latest('id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(\App\Models\PurchaseInvoice::class, 'project_id')
            ->where('invoice_type', \App\Models\PurchaseInvoice::TYPE_ORDER)
            ->latest('invoice_date')
            ->latest('id');
    }

    public function bills()
    {
        return $this->hasMany(\App\Models\PurchaseInvoice::class, 'project_id')
            ->where('invoice_type', \App\Models\PurchaseInvoice::TYPE_BILL)
            ->latest('invoice_date')
            ->latest('id');
    }

    public function publicShipments()
    {
        return $this->hasMany(\App\Models\Shipment::class, 'project_id')
            ->where('client_id', $this->client_id)
            ->where('show_client_portal', true)
            ->latest('id');
    }

    public function logs()
    {
        return $this->hasMany(ProjectLog::class)->latest('id');
    }

    /**
     * What the client has been asked, and what they said.
     *
     * Read-only relations: nothing here creates or deletes a feedback row, and
     * `destroy()` deliberately does not touch them — an answer is the client's
     * words about us and outlives the project it was about (the foreign key
     * nulls `project_id` instead of cascading).
     */
    public function feedbackRequests()
    {
        return $this->hasMany(FeedbackRequest::class)->latest('id');
    }

    public function feedbackResponses()
    {
        return $this->hasMany(FeedbackResponse::class)->latest('submitted_at');
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

    /**
     * What the project is worth: the documents raised on it, not a figure typed
     * on the project. That is the tax invoices the client owes plus the
     * proformas no tax invoice has carried yet — `SalesInvoice::
     * countsTowardsProject()` is the one rule, so a cancelled invoice and a
     * converted proforma are both off, and neither is counted twice.
     */
    public function estimatedValue(): float
    {
        return $this->documentsValue('taxInvoices', 'proformaInvoices');
    }

    /**
     * What the project may spend: the purchase orders placed for it plus the
     * bills recorded against it, read by `PurchaseInvoice::countsTowardsProject()`
     * — an order that became a bill is carried by that bill, and a cancelled
     * document is on neither figure.
     */
    public function budgetAmount(): float
    {
        return $this->documentsValue('purchaseOrders', 'bills');
    }

    /**
     * The documents a side of the money is read from, summed in the project's
     * currency. The relations are eager-loaded by every screen that lists
     * projects, so a page of rows costs four queries and not four per row.
     */
    private function documentsValue(string ...$relations): float
    {
        $total = 0.0;

        foreach ($relations as $relation) {
            if (! $this->documentRelationAvailable($relation)) {
                continue;
            }

            $documents = $this->relationLoaded($relation) ? $this->{$relation} : $this->{$relation}()->get();

            foreach ($documents as $document) {
                if ($document->countsTowardsProject()) {
                    $total += $this->documentValue($document);
                }
            }
        }

        return round($total, 2);
    }

    /**
     * A document counts in the project's currency. A project's documents are
     * normally raised in its own currency; when one is not, it counts at the
     * rate it was raised at, and a document with no rate counts at face value —
     * the same reading the rest of the app gives a missing rate.
     */
    private function documentValue(Model $document): float
    {
        $value = (float) $document->total_amount;

        return (string) $document->currency === (string) $this->currency
            ? $value
            : $value * (float) ($document->exchange_rate ?: 1);
    }

    /** A document module may not be installed on a deployment that predates it. */
    private function documentRelationAvailable(string $relation): bool
    {
        [$model, $table] = in_array($relation, ['taxInvoices', 'proformaInvoices'], true)
            ? [\App\Models\SalesInvoice::class, 'sales_invoices']
            : [\App\Models\PurchaseInvoice::class, 'purchase_invoices'];

        return class_exists($model) && Schema::hasTable($table) && Schema::hasColumn($table, 'project_id');
    }

    /**
     * What the project is worth and what has moved against it: the value comes
     * from the documents (`estimatedValue()`), the movement from the ledger —
     * a credit is money in, a debit is money out, and the separate payment
     * entries that used to be added on top are gone.
     */
    public function paymentTotals(): array
    {
        /* One name for the ledger relation: it is `cashflowEntries`, here and
           in every eager load, so the loaded rows are the ones counted. */
        $cashflows = $this->relationLoaded('cashflowEntries')
            ? $this->cashflowEntries
            : $this->cashflowEntries()->get();
    
        $inward = (float) $cashflows->where('transaction_type', 'credit')->sum('credit_amount');
        $outward = (float) $cashflows->where('transaction_type', 'debit')->sum('debit_amount');
    
        return [
            'inward' => $inward,
            'outward' => $outward,
            'net' => $inward - $outward,
            'outstanding' => max($this->estimatedValue() - $inward, 0),
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
