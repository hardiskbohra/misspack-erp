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
    /**
     * Every question the record page answers, in the order the office asks it:
     * what is being made, how far it has got, what it is worth, what it
     * carried, what was said, what happened. Each is a URL, not a button — so
     * a panel can be shared, bookmarked, opened in a window, and a form posted
     * from one tab returns to that tab (the sub-actions all redirect `back()`).
     */
    private const SHOW_TABS = [
        'overview' => 'Overview',
        'products' => 'Products',
        'milestones' => 'Milestones',
        'payments' => 'Payments',
        'shipments' => 'Shipments',
        'attachments' => 'Documents',
        'invoices' => 'Invoices',
        'comments' => 'Comments',
        'tracking' => 'Activities',
        'feedback' => 'Feedback',
        'logs' => 'Logs',
    ];

    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $clientId = $request->query('client_id', 'all');
        $health = $request->query('health', 'all');

        /* The money column reads the project's ledger entries — the ledger is
           the source of truth for project-level money — and the row's quick
           view reads the same totals: loaded here, the list costs one query
           per relation instead of two per project. */
        $with = ['products', 'products.milestones', 'cashflowEntries', 'assignedUser'];
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
            ->paginate(25)
            ->withQueryString();

        /* One grouped query answers the chips, the figures and the drawer. The
           four figures used to be four separate COUNT(*) round trips, and a chip
           with a count beside it needs the same numbers per status anyway. */
        $statusCounts = Project::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $healthCounts = Project::query()
            ->selectRaw('health, COUNT(*) as aggregate')
            ->groupBy('health')
            ->pluck('aggregate', 'health');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'waiting' => (int) ($statusCounts['waiting_client'] ?? 0) + (int) ($statusCounts['waiting_vendor'] ?? 0),
            'completed' => (int) ($statusCounts['completed'] ?? 0),
        ];

        return view('projects.index', array_merge($this->sharedData(), compact(
            'projects', 'stats', 'statusCounts', 'healthCounts', 'search', 'status', 'clientId', 'health'
        )));
    }

    public function create(): View
    {
        $project = new Project([
            'project_number' => $this->makeProjectNumber(),
            'client_id' => null,
            'name' => 'New Project',
            'status' => 'planned',
            'stage' => 'kickoff',
            'priority' => 'normal',
            'health' => 'green',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addDays(30)->toDateString(),
            'currency' => 'INR',
            'estimated_value' => 0,
            'budget_amount' => 0,
            'progress_percent' => 0,
            'show_client_portal' => true,
        ]);

        return view('projects.form', array_merge($this->sharedData(), [
            'project' => $project,
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

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data);

            $this->writeLog($project, 'created', 'Project created', 'Project was created from the deal/project form.', null, $project->toArray(), false);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'priority' => ['nullable', Rule::in(array_keys(Project::priorityOptions()))],
        ]);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create([
                'project_number' => $this->makeProjectNumber(),
                'client_id' => $data['client_id'],
                'name' => $data['name'],
                'status' => 'planned',
                'stage' => 'kickoff',
                'priority' => $data['priority'] ?? 'normal',
                'health' => 'green',
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'target_date' => $data['target_date'] ?? now()->addDays(30)->toDateString(),
                'currency' => 'INR',
                'estimated_value' => 0,
                'progress_percent' => 0,
                'show_client_portal' => true,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->writeLog($project, 'created', 'Quick project created', 'Project was created from quick create modal.', null, $project->toArray(), false);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', 'Quick project created successfully.');
    }

    public function show(Request $request, Project $project): View
    {
        $tab = (string) $request->query('tab', 'overview');
        $tab = array_key_exists($tab, self::SHOW_TABS) ? $tab : 'overview';

        $with = [
            'products.assignee', 'products.attachments', 'products.comments',
            'comments.user', 'comments.product',
            'attachments.product', 'attachments.uploader',
            'trackingUpdates.product', 'trackingUpdates.creator',
            'logs.user', 'logs.product', 'assignedUser', 'creator',
        ];
        if (class_exists(\App\Models\ProjectMilestone::class) && Schema::hasTable('project_milestones')) {
            $with[] = 'milestones.product';
            $with[] = 'milestones.owner';
            $with[] = 'milestones.creator';
        }

        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }
        if ($this->productModelAvailable()) {
            $with[] = 'products.product';
        }
        if ($this->cashflowEntryModelAvailable() && Schema::hasColumn('cashflow_entries', 'project_id')) {
            $with[] = 'cashflowEntries';
        }

        if ($this->shipmentModelAvailable()) {
            $with[] = 'shipments';
        }

        /* The Invoices tab: the money documents the modules raised against this
           project. A project does not raise one itself — the tag `project_id`
           is the whole link — so the tab reads the same rows the invoices
           listings do, and each family arrives with its ledger rows loaded
           (a document's received amount is the ledger's, not a second column). */
        foreach ($this->documentRelations() as $relation => [$table, $deeper]) {
            if ($this->documentTableAvailable($table)) {
                $with[] = $relation;
                $with[] = $relation.'.payments';
                foreach ($deeper as $path) {
                    $with[] = $relation.'.'.$path;
                }
            }
        }

        /* The feedback tab: the asks and what came back. Guarded like every other
           optional module on this page, so a deployment without the migrations
           renders the project instead of failing on a missing table. */
        if (class_exists(\App\Models\FeedbackRequest::class) && Schema::hasTable('feedback_requests')) {
            $with[] = 'feedbackRequests.response.answers';
            $with[] = 'feedbackRequests.response.actions';
        }

        $project->load($with);
        if (! $project->relationLoaded('milestones')) {
            $project->setRelation('milestones', collect());
        }
        if (! $project->relationLoaded('shipments')) {
            $project->setRelation('shipments', collect());
        }
        if (! $project->relationLoaded('feedbackRequests')) {
            $project->setRelation('feedbackRequests', collect());
        }
        foreach (array_keys($this->documentRelations()) as $relation) {
            if (! $project->relationLoaded($relation)) {
                $project->setRelation($relation, collect());
            }
        }

        $cashflowCount = $project->relationLoaded('cashflowEntries') ? $project->cashflowEntries->count() : 0;

        return view('projects.show', array_merge($this->sharedData(), [
            'project' => $project,
            'tabs' => self::SHOW_TABS,
            'tab' => $tab,
            'tabCounts' => [
                'products' => $project->products->count(),
                'milestones' => $project->milestones->count(),
                'payments' => $cashflowCount,
                'shipments' => $project->shipments->count(),
                'attachments' => $project->attachments->count(),
                'comments' => $project->comments->count(),
                'tracking' => $project->trackingUpdates->count(),
                'invoices' => collect(['taxInvoices', 'proformaInvoices', 'purchaseOrders', 'bills'])
                    ->sum(fn (string $relation) => $project->{$relation}->count()),
                'feedback' => $project->relationLoaded('feedbackRequests') ? $project->feedbackRequests->count() : 0,
                'logs' => $project->logs->count(),
            ],
            'recordUrl' => fn (string $key) => route('projects.show', ['project' => $project, 'tab' => $key]),
        ]));
    }

    public function edit(Project $project): View
    {
        $with = ['assignedUser'];
        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }
        $project->load($with);

        return view('projects.form', array_merge($this->sharedData(), [
            'project' => $project,
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

        /* Marking a project complete is the moment the feedback ask is worth
           making, so the message says where it is. An offer, never automatic: a
           project can be closed before the client has the goods in hand. */
        if ($data['status'] === 'completed'
            && class_exists(\App\Models\FeedbackRequest::class)
            && Schema::hasTable('feedback_requests')
            && ! \App\Models\FeedbackRequest::query()->forProject($project->id)->live()->exists()) {
            return back()->with('success', 'Project completed. Ask for feedback while it is fresh — the Feedback tab on this project has the link.');
        }

        return back()->with('success', 'Project status updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project) {

            // Delete child records
            $project->comments()->delete();
            $project->attachments()->delete();
            $project->trackingUpdates()->delete();
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

    private function cashflowEntryModelAvailable(): bool
    {
        return class_exists(\App\Models\CashflowEntry::class) && Schema::hasTable('cashflow_entries');
    }

    /**
     * The four documents the Invoices tab reads, relation => table.
     *
     * One list, so the eager load, the empty-collection fallback and the tab's
     * tally cannot disagree about what the tab holds. The type words live on
     * the models (`SalesInvoice::typeOptions()`, `PurchaseInvoice::DOC_LABELS`)
     * and the relations carry them; this list only names the relations, the
     * table each reads and the row reads the tab makes beyond the ledger
     * (a purchase document names its vendor).
     */
    private function documentRelations(): array
    {
        return [
            'taxInvoices' => ['sales_invoices', []],
            'proformaInvoices' => ['sales_invoices', []],
            'purchaseOrders' => ['purchase_invoices', ['vendor']],
            'bills' => ['purchase_invoices', ['vendor']],
        ];
    }

    /** A document module is readable when its model is installed and its rows carry the project tag. */
    private function documentTableAvailable(string $table): bool
    {
        $model = $table === 'sales_invoices' ? \App\Models\SalesInvoice::class : \App\Models\PurchaseInvoice::class;

        return class_exists($model)
            && Schema::hasTable($table)
            && Schema::hasColumn($table, 'project_id');
    }

    private function shipmentModelAvailable(): bool
    {
        return class_exists(\App\Models\Shipment::class)
            && Schema::hasTable('shipments')
            && Schema::hasColumn('shipments', 'project_id');
    }
}
