<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectProduct;
use App\Models\User;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $clientId = $request->query('client_id', 'all');
        $health = $request->query('health', 'all');

        $with = ['products', 'products.milestones', 'payments', 'assignedUser'];
        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }

        $projects = Project::query()
            ->with($with)
            ->search($search)
            ->when($status !== 'all', function ($q) use ($status) { $q->where('status', $status); })
            ->when($clientId !== 'all', function ($q) use ($clientId) { $q->where('client_id', $clientId); })
            ->when($health !== 'all', function ($q) use ($health) { $q->where('health', $health); })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => Project::count(),
            'in_progress' => Project::where('status', 'in_progress')->count(),
            'waiting' => Project::whereIn('status', ['waiting_client', 'waiting_vendor'])->count(),
            'completed' => Project::where('status', 'completed')->count(),
        ];

        return view('projects.index', array_merge($this->sharedData(), compact('projects', 'stats', 'search', 'status', 'clientId', 'health')));
    }

    public function create(Request $request): View
    {
        $quote = $this->customerQuoteFromRequest($request);

        $project = new Project([
            'project_number' => $this->makeProjectNumber(),
            'customer_quote_id' => $quote ? $quote->id : null,
            'client_id' => $quote ? ($quote->client_id ?? null) : null,
            'name' => $quote ? ($quote->title ?? 'New Project') : 'New Project',
            'status' => 'planned',
            'stage' => 'quote_finalised',
            'priority' => 'normal',
            'health' => 'green',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addDays(30)->toDateString(),
            'currency' => $quote ? ($quote->currency ?? 'INR') : 'INR',
            'estimated_value' => $quote ? ($quote->total_amount ?? 0) : 0,
            'budget_amount' => 0,
            'progress_percent' => 0,
            'show_client_portal' => true,
            'scope_summary' => $quote ? ($quote->notes ?? null) : null,
            'deliverables' => $quote ? ($quote->delivery_terms ?? null) : null,
            'client_notes' => $quote ? ($quote->delivery_time ?? null) : null,
        ]);

        return view('projects.form', array_merge($this->sharedData(), [
            'project' => $project,
            'quote' => $quote,
            'isEdit' => false,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['project_number'] = $data['project_number'] ?: $this->makeProjectNumber();
        $data['show_client_portal'] = $request->has('show_client_portal');
        $data['created_by'] = Auth::id();

        if (($data['status'] ?? null) === 'completed' && empty($data['completed_at'])) {
            $data['completed_at'] = now();
            $data['progress_percent'] = 100;
        }

        $quote = $this->customerQuoteFromId($data['customer_quote_id'] ?? null);

        $project = DB::transaction(function () use ($data, $quote, $request) {
            $project = Project::create($data);

            $this->writeLog($project, 'created', 'Project created', 'Project was created from the deal/project form.', null, $project->toArray(), false);

            if ($quote && $request->has('import_quote_items')) {
                $this->importQuoteItems($project, $quote);
            }

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'customer_quote_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::in(array_keys(Project::priorityOptions()))],
        ]);

        $quote = $this->customerQuoteFromId($data['customer_quote_id'] ?? null);

        $project = DB::transaction(function () use ($data, $quote) {
            $project = Project::create([
                'project_number' => $this->makeProjectNumber(),
                'client_id' => $data['client_id'],
                'customer_quote_id' => $data['customer_quote_id'] ?? null,
                'name' => $data['name'],
                'status' => 'planned',
                'stage' => 'quote_finalised',
                'priority' => $data['priority'] ?? 'normal',
                'health' => 'green',
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'target_date' => $data['target_date'] ?? now()->addDays(30)->toDateString(),
                'currency' => $quote ? ($quote->currency ?? 'INR') : 'INR',
                'estimated_value' => $quote ? ($quote->total_amount ?? 0) : 0,
                'progress_percent' => 0,
                'show_client_portal' => true,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->writeLog($project, 'created', 'Quick project created', 'Project was created from quick create modal.', null, $project->toArray(), false);

            if ($quote) {
                $this->importQuoteItems($project, $quote);
            }

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Quick project created successfully.');
    }

    public function show(Project $project): View
    {
        $with = [
            'products.assignee', 'products.attachments', 'products.comments',
            'comments.user', 'comments.product',
            'attachments.product', 'attachments.uploader',
            'trackingUpdates.product', 'trackingUpdates.creator',
            'payments.creator', 'logs.user', 'logs.product', 'assignedUser', 'creator',
        ];
        if (class_exists(\App\Models\ProjectMilestone::class) && Schema::hasTable('project_milestones')) {
            $with[] = 'milestones.product';
            $with[] = 'milestones.owner';
            $with[] = 'milestones.creator';
        }

        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }
        if ($this->customerQuoteModelAvailable()) {
            $with[] = 'customerQuote';
        }
        if ($this->productModelAvailable()) {
            $with[] = 'products.product';
        }
        if ($this->cashflowEntryModelAvailable() && Schema::hasColumn('cashflow_entries', 'project_id')) {
            $with[] = 'cashflowEntries';
        }

        $project->load($with);
        if (! $project->relationLoaded('milestones')) {
            $project->setRelation('milestones', collect());
        }

        return view('projects.show', array_merge($this->sharedData(), ['project' => $project]));
    }

    public function edit(Project $project): View
    {
        $with = ['assignedUser'];
        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }
        if ($this->customerQuoteModelAvailable()) {
            $with[] = 'customerQuote';
        }
        $project->load($with);

        return view('projects.form', array_merge($this->sharedData(), [
            'project' => $project,
            'quote' => $project->relationLoaded('customerQuote') ? $project->customerQuote : null,
            'isEdit' => true,
        ]));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project);
        $data['show_client_portal'] = $request->has('show_client_portal');

        if (($data['status'] ?? null) === 'completed' && ! $project->completed_at) {
            $data['completed_at'] = now();
            $data['progress_percent'] = 100;
        }
        if (($data['status'] ?? null) !== 'completed') {
            $data['completed_at'] = null;
        }

        DB::transaction(function () use ($project, $data) {
            $old = $project->only(['name', 'status', 'stage', 'priority', 'health', 'progress_percent', 'target_date']);
            $project->update($data);
            $this->writeLog($project, 'updated', 'Project updated', 'Project details were updated.', $old, $project->fresh()->toArray(), false);
        });

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }

    public function updateStatus(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Project::statusOptions()))],
            'stage' => ['required', Rule::in(array_keys(Project::stageOptions()))],
            'health' => ['required', Rule::in(array_keys(Project::healthOptions()))],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $old = $project->only(['status', 'stage', 'health', 'progress_percent', 'completed_at']);
        if ($data['status'] === 'completed') {
            $data['progress_percent'] = 100;
            $data['completed_at'] = $project->completed_at ?: now();
        } else {
            $data['completed_at'] = null;
        }

        $project->update($data);
        $this->writeLog($project, 'status_updated', 'Project status updated', 'Status/stage/progress was updated.', $old, $data, true);

        return back()->with('success', 'Project status updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project) {

            // Delete child records
            $project->comments()->delete();
            $project->attachments()->delete();
            $project->trackingUpdates()->delete();
            $project->payments()->delete();
            $project->logs()->delete();
    
            // If cashflow relation exists
            if (method_exists($project, 'cashflowEntries')) {
                $project->cashflowEntries()->update([
                    'project_id' => null,
                ]);
            }
    
            // Finally delete project
            $project->delete();
        });
    
        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    private function validatedData(Request $request, ?Project $project = null): array
    {
        $uniqueProjectNumber = Rule::unique('projects', 'project_number');
        if ($project) {
            $uniqueProjectNumber->ignore($project->id);
        }

        return $request->validate([
            'project_number' => ['nullable', 'string', 'max:255', $uniqueProjectNumber],
            'client_id' => ['required', 'integer'],
            'customer_quote_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Project::statusOptions()))],
            'stage' => ['required', Rule::in(array_keys(Project::stageOptions()))],
            'priority' => ['required', Rule::in(array_keys(Project::priorityOptions()))],
            'health' => ['required', Rule::in(array_keys(Project::healthOptions()))],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date'],
            'currency' => ['required', Rule::in(array_keys(Project::currencyOptions()))],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scope_summary' => ['nullable', 'string'],
            'deliverables' => ['nullable', 'string'],
            'client_notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
        ]);
    }

    private function sharedData(): array
    {
        return [
            'clients' => $this->clients(),
            'vendors' => $this->vendors(),
            'products' => $this->products(),
            'users' => User::query()->orderBy('name')->get(),
            'quotes' => $this->customerQuotes(),
            'cashflowEntries' => $this->cashflowEntries(),
            'cashflowAccounts' => $this->cashflowAccounts(),
            'cashflowCategories' => $this->cashflowCategories(),
            'statusOptions' => Project::statusOptions(),
            'stageOptions' => Project::stageOptions(),
            'priorityOptions' => Project::priorityOptions(),
            'healthOptions' => Project::healthOptions(),
            'currencyOptions' => Project::currencyOptions(),
            'productStatusOptions' => \App\Models\ProjectProduct::statusOptions(),
            'productStageOptions' => \App\Models\ProjectProduct::stageOptions(),
            'attachmentCategoryOptions' => \App\Models\ProjectAttachment::categoryOptions(),
            'trackingStatusOptions' => \App\Models\ProjectTrackingUpdate::statusOptions(),
            'paymentTypeOptions' => \App\Models\ProjectPayment::transactionTypeOptions(),
            'paymentStatusOptions' => \App\Models\ProjectPayment::statusOptions(),
            'paymentModeOptions' => \App\Models\ProjectPayment::paymentModeOptions(),
            'milestoneOptions' => class_exists(\App\Models\ProjectMilestone::class) ? \App\Models\ProjectMilestone::milestoneOptions() : [],
            'milestoneStatusOptions' => class_exists(\App\Models\ProjectMilestone::class) ? \App\Models\ProjectMilestone::statusOptions() : [],
        ];
    }

    private function makeProjectNumber(): string
    {
        $prefix = 'PRJ-';
        $next = str_pad((string) (Project::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (Project::where('project_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function importQuoteItems(Project $project, $quote): void
    {
        if (! method_exists($quote, 'items')) {
            return;
        }

        $quote->load('items');
        foreach ($quote->items as $index => $item) {
            ProjectProduct::create([
                'project_id' => $project->id,
                'product_id' => $item->product_id ?? null,
                'product_name' => $item->product_name ?: 'Product',
                'sku' => null,
                'quantity' => $item->quantity ?: 1,
                'unit' => $item->unit ?: 'pcs',
                'unit_price' => $item->unit_price ?: 0,
                'currency' => $project->currency,
                'status' => 'planned',
                'stage' => 'pending',
                'notes' => trim(($item->description ? $item->description."\n" : '').($item->remarks ?: '')),
                'sort_order' => $index + 1,
            ]);
        }

        $this->writeLog($project, 'quote_imported', 'Quote products imported', 'Accepted quote products were imported into this project.', null, ['quote_id' => $quote->id], false);
    }

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $oldValues = null, ?array $newValues = null, bool $isPublic = false): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'actor_type' => Auth::check() ? 'internal' : 'system',
            'actor_name' => Auth::check() ? (Auth::user()->name ?? 'Internal Team') : 'System',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'is_public' => $isPublic,
        ]);
    }

    private function customerQuoteFromRequest(Request $request)
    {
        $id = $request->query('customer_quote_id', $request->query('quote_id'));
        return $this->customerQuoteFromId($id);
    }

    private function customerQuoteFromId($id)
    {
        if (! $id || ! $this->customerQuoteModelAvailable()) {
            return null;
        }

        return \App\Models\CustomerQuote::with('items')->find($id);
    }

    private function clients()
    {
        if (! $this->clientModelAvailable()) {
            return collect();
        }

        return \App\Models\Client::query()->orderBy('company_name')->get();
    }

    private function vendors()
    {
        if (! $this->vendorModelAvailable()) {
            return collect();
        }

        return \App\Models\Vendor::query()->orderBy('contact_person_name')->get();
    }

    private function products()
    {
        if (! $this->productModelAvailable()) {
            return collect();
        }

        return \App\Models\Product::query()->where('status', 'active')->orderBy('name')->get();
    }

    private function customerQuotes()
    {
        if (! $this->customerQuoteModelAvailable()) {
            return collect();
        }

        return \App\Models\CustomerQuote::query()->where('status', 'accepted')->latest('id')->get();
    }

    private function cashflowEntries()
    {
        if (! $this->cashflowEntryModelAvailable()) {
            return collect();
        }

        return \App\Models\CashflowEntry::query()->latest('entry_date')->limit(100)->get();
    }

    private function cashflowAccounts()
    {
        if (! class_exists(\App\Models\CashflowAccount::class) || ! Schema::hasTable('cashflow_accounts')) {
            return collect();
        }

        return \App\Models\CashflowAccount::query()->orderBy('account_name')->get();
    }

    private function cashflowCategories()
    {
        if (! class_exists(\App\Models\CashflowCategory::class) || ! Schema::hasTable('cashflow_categories')) {
            return collect();
        }

        return \App\Models\CashflowCategory::query()->orderBy('name')->get();
    }

    private function clientModelAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }

    private function vendorModelAvailable(): bool
    {
        return class_exists(\App\Models\Vendor::class) && Schema::hasTable('vendors');
    }

    private function productModelAvailable(): bool
    {
        return class_exists(\App\Models\Product::class) && Schema::hasTable('products');
    }

    private function customerQuoteModelAvailable(): bool
    {
        return class_exists(\App\Models\CustomerQuote::class) && Schema::hasTable('customer_quotes');
    }

    private function cashflowEntryModelAvailable(): bool
    {
        return class_exists(\App\Models\CashflowEntry::class) && Schema::hasTable('cashflow_entries');
    }
}
