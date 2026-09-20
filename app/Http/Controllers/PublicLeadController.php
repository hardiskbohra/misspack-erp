<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadMasterOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicLeadController extends Controller
{
    public function create(): View
    {
        return view('leads.public-create', [
            'sourceOptions' => $this->masterOptions('lead_source', Lead::sourceOptions()),
            'finishOptions' => $this->masterOptions('finish', Lead::finishOptions()),
            'printingOptions' => $this->masterOptions('printing', Lead::printingOptions()),
            'currencyOptions' => $this->masterOptions('currency', Lead::currencyOptions()),
        ]);
    }

    public function show(string $token): View
    {
        $lead = Lead::query()
            ->where('public_token', $token)
            ->with('attachments')
            ->firstOrFail();

        return view('leads.public-show', [
            'lead' => $lead,
            'finishOptions' => $this->masterOptions('finish', Lead::finishOptions()),
            'printingOptions' => $this->masterOptions('printing', Lead::printingOptions()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Simple honeypot field. Real users will not fill this hidden field.
        if ($request->filled('website_url')) {
            return back()->with('success', 'Thank you. Your requirement has been submitted.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_company_name' => ['required', 'string', 'max:255'],
            'client_contact_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_mobile' => ['required', 'string', 'max:40'],
            'lead_source' => ['nullable', Rule::in($this->masterKeys('lead_source', array_keys(Lead::sourceOptions())))],

            'product_name' => ['required', 'string', 'max:255'],
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
            'custom_color_required' => ['nullable', 'boolean'],
            'custom_color_specification' => ['nullable', 'string'],

            'target_price' => ['nullable', 'numeric', 'min:0'],
            'target_currency' => ['nullable', Rule::in($this->masterKeys('currency', array_keys(Lead::currencyOptions())))],
            'required_delivery_date' => ['nullable', 'date'],
            'sales_notes' => ['nullable', 'string'],

            'attachments.*' => ['nullable', 'file', 'max:20480'],
            'attachment_links' => ['nullable', 'string'],
        ]);

        $data = $this->prepareData($request, $data);
        $data['lead_number'] = $this->makeLeadNumber();
        $data['lead_source'] = $data['lead_source'] ?? 'website';
        $data['priority'] = 'medium';
        $data['status'] = Lead::STATUS_NEW;
        $data['target_currency'] = $data['target_currency'] ?? 'INR';
        $data['created_by'] = null;

        DB::transaction(function () use ($request, $data) {
            $lead = Lead::create($data);
            $this->storeAttachments($request, $lead);
        });

        return redirect()
            ->route('leads.public.create')
            ->with('success', 'Thank you. Your requirement has been submitted successfully. Our team will contact you soon.');
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

    private function prepareData(Request $request, array $data): array
    {
        unset($data['product_image'], $data['attachments'], $data['attachment_links'], $data['website_url']);

        $quantities = collect(explode(',', (string) ($data['quote_quantities_text'] ?? '')))
            ->map(function ($value) {
                return trim($value);
            })
            ->filter(function ($value) {
                return $value !== '' && is_numeric($value);
            })
            ->map(function ($value) {
                return (int) $value;
            })
            ->values()
            ->all();

        unset($data['quote_quantities_text']);

        $data['quote_quantities'] = $quantities ?: null;
        $data['ready_stock_required'] = $request->boolean('ready_stock_required');
        $data['custom_color_required'] = $request->boolean('custom_color_required');
        $data['capacity_unit'] = $data['capacity_unit'] ?? 'ml';

        if ($request->hasFile('product_image')) {
            $data['product_image_path'] = $request->file('product_image')->store('leads/products', 'public');
        }

        return $data;
    }

    private function storeAttachments(Request $request, Lead $lead): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $mimeType = (string) $file->getMimeType();

            $lead->attachments()->create([
                'attachment_type' => str_starts_with($mimeType, 'image/') ? 'photo' : (str_starts_with($mimeType, 'video/') ? 'video' : 'document'),
                'title' => $file->getClientOriginalName(),
                'file_path' => $file->store('leads/attachments', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => null,
            ]);
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('attachment_links')) as $url) {
            $url = trim($url);

            if ($url === '') {
                continue;
            }

            $lead->attachments()->create([
                'attachment_type' => 'link',
                'title' => $url,
                'external_url' => $url,
                'uploaded_by' => null,
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
}
