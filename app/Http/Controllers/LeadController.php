<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadMasterOption;
use App\Models\VendorQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $priority = $request->query('priority', 'all');
        $source = $request->query('source', 'all');
        $assignedTo = $request->query('assigned_to', 'all');

        $query = Lead::query()
            ->with(['assignee', 'creator'])
            ->withCount('vendorQuotes')
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($priority !== 'all', fn ($q) => $q->where('priority', $priority))
            ->when($source !== 'all', fn ($q) => $q->where('lead_source', $source))
            ->when($assignedTo !== 'all', fn ($q) => $q->where('assigned_to', $assignedTo));

        $leads = $query->latest('id')->paginate(10)->withQueryString();

        $stats = [
            'total' => Lead::count(),
            'new' => Lead::where('status', Lead::STATUS_NEW)->count(),
            'sourcing' => Lead::where('status', Lead::STATUS_SOURCING)->count(),
            'quoted' => Lead::where('status', Lead::STATUS_QUOTED)->count(),
            'won' => Lead::where('status', Lead::STATUS_WON)->count(),
        ];

        return view('leads.index', array_merge($this->sharedData(), compact('leads', 'stats', 'search', 'status', 'priority', 'source', 'assignedTo')));
    }

    public function create(): View
    {
        $lead = new Lead([
            'lead_number' => $this->makeLeadNumber(),
            'lead_source' => 'whatsapp',
            'priority' => 'medium',
            'status' => Lead::STATUS_NEW,
            'capacity_unit' => 'ml',
            'target_currency' => 'INR',
        ]);

        return view('leads.form', array_merge($this->sharedData(), compact('lead')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->prepareData($request, $data);
        $data['lead_number'] = $data['lead_number'] ?: $this->makeLeadNumber();
        $data['created_by'] = Auth::id();

        $lead = DB::transaction(function () use ($request, $data) {
            $lead = Lead::create($data);
            $this->storeAttachments($request, $lead);
            return $lead;
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_company_name' => ['nullable', 'string', 'max:255'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_mobile' => ['nullable', 'string', 'max:40'],
            'lead_source' => ['required', Rule::in($this->masterKeys('lead_source', array_keys(Lead::sourceOptions())))],
            'priority' => ['required', Rule::in($this->masterKeys('lead_priority', array_keys(Lead::priorityOptions())))],
            'status' => ['required', Rule::in($this->masterKeys('lead_status', array_keys(Lead::statusOptions())))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'capacity_value' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:20'],
            'required_quantity' => ['nullable', 'integer', 'min:0'],
            'finish_required' => ['nullable', Rule::in($this->masterKeys('finish', array_keys(Lead::finishOptions())))],
            'printing_required' => ['nullable', Rule::in($this->masterKeys('printing', array_keys(Lead::printingOptions())))],
            'ready_stock_required' => ['nullable', 'boolean'],
            'sales_notes' => ['nullable', 'string'],
        ]);

        unset($data['product_image']);
        $data['lead_number'] = $this->makeLeadNumber();
        $data['target_currency'] = 'INR';
        $data['created_by'] = Auth::id();
        $data['ready_stock_required'] = $request->boolean('ready_stock_required');

        if ($request->hasFile('product_image')) {
            $data['product_image_path'] = $request->file('product_image')->store('leads/products', 'public');
        }

        $lead = Lead::create($data);

        return redirect()->route('leads.show', $lead)->with('success', 'Quick lead created successfully.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in($this->masterKeys('lead_status', array_keys(Lead::statusOptions())))],
        ]);

        $lead->update([
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Lead status updated successfully.');
    }

    public function show(Lead $lead): View
    {
        $with = ['attachments', 'vendorQuotes.prices', 'customerQuotes.items', 'comments.creator', 'assignee', 'creator'];
        if ($this->clientModelAvailable()) $with[] = 'client';
        $lead->load($with);

        return view('leads.show', array_merge($this->sharedData(), compact('lead')));
    }

    public function image(Lead $lead): View
    {
        $lead->load(['assignee', 'creator']);

        return view('leads.image', [
            'lead' => $lead,
            'imagePath' => $lead->product_image_path,
            'title' => $lead->title,
            'subtitle' => $lead->lead_number.' · '.($lead->product_name ?: 'Product Image'),
            'backUrl' => route('leads.show', $lead),
        ]);
    }

    public function edit(Lead $lead): View
    {
        $lead->load('attachments');

        return view('leads.form', array_merge($this->sharedData(), compact('lead')));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $this->validatedData($request, $lead);
        $data = $this->prepareData($request, $data, $lead);

        DB::transaction(function () use ($request, $lead, $data) {
            $lead->update($data);
            $this->storeAttachments($request, $lead);
        });

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated successfully.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        if ($lead->product_image_path) Storage::disk('public')->delete($lead->product_image_path);
        foreach ($lead->attachments as $attachment) {
            if ($attachment->file_path) Storage::disk('public')->delete($attachment->file_path);
        }
        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted successfully.');
    }

    private function validatedData(Request $request, ?Lead $lead = null): array
    {
        return $request->validate([
            'lead_number' => ['nullable', 'string', 'max:255', 'unique:leads,lead_number,'.($lead?->id ?? 'NULL')],
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer'],
            'client_company_name' => ['nullable', 'string', 'max:255'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_mobile' => ['nullable', 'string', 'max:40'],
            'lead_source' => ['required', Rule::in($this->masterKeys('lead_source', array_keys(Lead::sourceOptions())))],
            'priority' => ['required', Rule::in($this->masterKeys('lead_priority', array_keys(Lead::priorityOptions())))],
            'status' => ['required', Rule::in($this->masterKeys('lead_status', array_keys(Lead::statusOptions())))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'product_description' => ['nullable', 'string'],
            'capacity_value' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:20'],
            'required_quantity' => ['nullable', 'integer', 'min:0'],
            'quantity_notes' => ['nullable', 'string'],
            'quote_quantities_text' => ['nullable', 'string', 'max:255'],
            'finish_required' => ['nullable', Rule::in($this->masterKeys('finish', array_keys(Lead::finishOptions())))],
            'printing_required' => ['nullable', Rule::in($this->masterKeys('printing', array_keys(Lead::printingOptions())))],
            'printing_details' => ['nullable', 'string'],
            'ready_stock_required' => ['nullable', 'boolean'],
            'ready_stock_color_requirement' => ['nullable', 'string'],
            'ready_stock_moq_notes' => ['nullable', 'string'],
            'custom_color_required' => ['nullable', 'boolean'],
            'custom_color_specification' => ['nullable', 'string'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'target_currency' => ['required', Rule::in($this->masterKeys('currency', array_keys(Lead::currencyOptions())))],
            'expected_order_date' => ['nullable', 'date'],
            'required_delivery_date' => ['nullable', 'date'],
            'sales_notes' => ['nullable', 'string'],
            'purchase_notes' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
            'attachment_links' => ['nullable', 'string'],
        ]);
    }

    private function prepareData(Request $request, array $data, ?Lead $lead = null): array
    {
        unset($data['product_image'], $data['attachments'], $data['attachment_links']);
        $quantities = collect(explode(',', (string) ($data['quote_quantities_text'] ?? '')))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '' && is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
        unset($data['quote_quantities_text']);
        $data['quote_quantities'] = $quantities ?: null;
        $data['ready_stock_required'] = $request->boolean('ready_stock_required');
        $data['custom_color_required'] = $request->boolean('custom_color_required');

        if ($request->hasFile('product_image')) {
            if ($lead?->product_image_path) Storage::disk('public')->delete($lead->product_image_path);
            $data['product_image_path'] = $request->file('product_image')->store('leads/products', 'public');
        }

        return $data;
    }

    private function storeAttachments(Request $request, Lead $lead): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) continue;
            $lead->attachments()->create([
                'attachment_type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'photo' : (str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'document'),
                'title' => $file->getClientOriginalName(),
                'file_path' => $file->store('leads/attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('attachment_links')) as $url) {
            $url = trim($url);
            if ($url === '') continue;
            $lead->attachments()->create([
                'attachment_type' => 'link',
                'title' => $url,
                'external_url' => $url,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function makeLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd').'-';
        $next = str_pad((string) (Lead::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;
        while (Lead::where('lead_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }
        return $number;
    }

    private function sharedData(): array
    {
        return [
            'statusOptions' => $this->masterOptions('lead_status', Lead::statusOptions()),
            'priorityOptions' => $this->masterOptions('lead_priority', Lead::priorityOptions()),
            'sourceOptions' => $this->masterOptions('lead_source', Lead::sourceOptions()),
            'finishOptions' => $this->masterOptions('finish', Lead::finishOptions()),
            'printingOptions' => $this->masterOptions('printing', Lead::printingOptions()),
            'currencyOptions' => $this->masterOptions('currency', Lead::currencyOptions()),
            'quoteStatusOptions' => $this->masterOptions('quote_status', VendorQuote::statusOptions()),
            'commentTypeOptions' => \App\Models\LeadComment::typeOptions(),
            'clients' => $this->clients(),
            'users' => \App\Models\User::query()->orderBy('id')->get(),
        ];
    }


    private function masterOptions(string $group, array $fallback = [], bool $activeOnly = true): array
    {
        if (! Schema::hasTable('lead_master_options')) {
            return $fallback;
        }

        $query = LeadMasterOption::query()->where('group', $group);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $options = $query->orderBy('sort_order')->orderBy('label')->pluck('label', 'key')->toArray();

        return $options ?: $fallback;
    }

    private function masterKeys(string $group, array $fallback): array
    {
        $fallbackOptions = array_combine($fallback, $fallback);

        return array_keys($this->masterOptions($group, $fallbackOptions ?: [], true));
    }

    private function clients()
    {
        if (! $this->clientModelAvailable()) return collect();
        return \App\Models\Client::query()->orderBy('company_name')->get();
    }

    private function clientModelAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }
}
