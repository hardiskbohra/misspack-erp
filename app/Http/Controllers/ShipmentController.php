<?php

namespace App\Http\Controllers;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\SalesInvoice;
use App\Models\SavedView;
use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\ShipmentCost;
use App\Models\ShipmentTrackingHistory;
use App\Services\ClientPortalNotifier;
use App\Services\SavedViews;
use App\Services\ShipmentCostCashflowSync;
use App\Services\ShipmentCostLedger;
use App\Services\ShipmentDocuments;
use App\Services\ShipmentPartyDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    /** Ceiling for the bulk sticker sheet — a 60-sticker run is already 8 A4 pages. */
    private const STICKER_SHEET_LIMIT = 60;

    public function index(Request $request): View
    {
        // Jumping back into a saved view simply re-runs its filters.
        if ($savedQuery = $this->resolveSavedView($request)) {
            return redirect()->route('shipments.index', $savedQuery);
        }

        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $currency = $request->query('currency', 'all');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $attention = $request->query('attention');

        // Open shipments first, finished ones below; date is the tie-breaker
        // inside each group (see Shipment::scopePriorityOrder).
        $shipments = $this->filteredQuery($request)
            ->with('creator')
            ->withCount('items')
            ->withCount('costs')
            ->withSum(['costs as cost_same_currency' => fn ($query) => $query->whereColumn('shipment_costs.currency', 'shipments.currency')], 'amount')
            ->withSum('costs as cost_inr_total', 'amount_in_inr')
            ->priorityOrder()
            ->paginate(50)
            ->withQueryString();

        $ledger = app(ShipmentCostLedger::class);

        // Total spent "as per the shown entries": everything matching the
        // current filters (not just this page). Cost heads are summed in the
        // currency they were billed in; shipments that still carry only the
        // single legacy figure contribute that instead. Currencies are listed
        // side by side and never added into one number.
        $spendByCurrency = $this->spendByCurrency($request);
        $spendEntries = $this->filteredQuery($request)
            ->where(function ($query) {
                $query->whereHas('costs')
                    ->orWhere(fn ($legacy) => $legacy->whereNotNull('shipment_cost')->whereDoesntHave('costs'));
            })
            ->count();

        // And the same total for the rows actually on screen, for the footer.
        $pageSpendByCurrency = [];
        foreach ($shipments->getCollection() as $shipment) {
            if ((int) $shipment->costs_count > 0) {
                $totals = $ledger->totals($shipment);

                foreach ($totals['by_currency'] as $code => $amount) {
                    $pageSpendByCurrency[$code] = round(($pageSpendByCurrency[$code] ?? 0) + $amount, 2);
                }

                continue;
            }

            if ($shipment->shipment_cost !== null) {
                $code = strtoupper((string) ($shipment->currency ?: 'INR'));
                $pageSpendByCurrency[$code] = round(($pageSpendByCurrency[$code] ?? 0) + (float) $shipment->shipment_cost, 2);
            }
        }

        $stats = [
            'total' => Shipment::count(),
            'domestic' => Shipment::where('shipment_type', Shipment::TYPE_DOMESTIC)->count(),
            'import' => Shipment::where('shipment_type', Shipment::TYPE_IMPORT)->count(),
            'in_transit' => Shipment::where('status', Shipment::STATUS_IN_TRANSIT)->count(),
            'custom_hold' => Shipment::where('status', Shipment::STATUS_CUSTOM_HOLD)->count(),
            'delayed' => Shipment::where('status', Shipment::STATUS_DELAYED)->count(),
            'out_for_delivery' => Shipment::where('status', Shipment::STATUS_OUT_DELIVERY)->count(),
            'delivered' => Shipment::where('status', Shipment::STATUS_DELIVERED)->count(),
        ];

        $savedViews = app(SavedViews::class);

        return view('shipments.index', [
            'shipments' => $shipments,
            'stats' => $stats,
            'spendByCurrency' => $spendByCurrency,
            'spendEntries' => $spendEntries,
            'pageSpendByCurrency' => $pageSpendByCurrency,
            'partyFields' => ShipmentPartyDirectory::PARTIES,
            'partyNames' => $this->partyNames(),
            'attention' => $attention,
            'attentionCounts' => $this->attentionCounts($request),
            'savedViews' => $savedViews->forUser(Auth::id(), 'shipments'),
            'documentTypes' => ShipmentDocuments::labels(),
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'currency' => $currency,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusOptions' => Shipment::statusOptions(),
            'typeOptions' => Shipment::typeOptions(),
            'currencyOptions' => Shipment::currencyOptions(),
            'costBorneByOptions' => Shipment::costBorneByOptions(),
            'modeOptions' => Shipment::modeOptions(),
        ]);
    }

    public function create(): View
    {
        $shipment = new Shipment([
            'shipment_number' => $this->makeShipmentNumber(),
            'shipment_type' => Shipment::TYPE_DOMESTIC,
            'shipment_mode' => 'courier',
            'pickup_date' => now()->toDateString(),
            'status' => Shipment::STATUS_PLANNING,
            'currency' => 'INR',
            'cost_borne_by' => 'misspack',
        ]);
        $shipment->setRelation('items', collect());
        $shipment->setRelation('attachments', collect());

        return view('shipments.form', $this->formData($shipment));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->validatePhotoAttachments($request);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachment_photos'], $data['notify_client'], $data['document_type']);

        /* a shipment can be created already delivered: same rule, same default */
        $data = Shipment::withDeliveryDefaults($data);
        $this->assertDeliveryDateAllowed($data['drop_date'] ?? null, $data['pickup_date'] ?? null);

        $shipment = DB::transaction(function () use ($request, $data, $items) {
            $data['shipment_number'] = $data['shipment_number'] ?: $this->makeShipmentNumber();
            $data['show_client_portal'] = $request->boolean('show_client_portal');
            $data['public_token'] = Str::random(48);
            $data['created_by'] = Auth::id();

            $shipment = Shipment::create($data);
            $this->syncItems($shipment, $items);
            $this->storePhotoAttachments($request, $shipment);
            $this->addHistory($shipment, $shipment->status, 'Shipment created', $shipment->from_city ?: $shipment->from_country);

            return $shipment;
        });

        return redirect()
            ->route('shipments.show', $shipment)
            ->with('success', 'Shipment created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identity_name' => ['required', 'string', 'max:255'],
            'shipment_type' => ['required', 'in:domestic,import,export'],
            'pickup_date' => ['nullable', 'date'],
            'drop_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'logistic_partner' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'bill_of_entry_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:planning,picked_up,in_transit,custom_hold,delayed,out_for_delivery,delivered,cancelled'],
            'shipment_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'in:INR,RMB,USD'],
            'cost_borne_by' => ['nullable', 'in:shipper,receiver,misspack'],
            'notes' => ['nullable', 'string'],
        ]);

        $shipment = DB::transaction(function () use ($data) {
            $data['shipment_number'] = $this->makeShipmentNumber();
            $data['shipment_mode'] = 'courier';
            $data['status'] = 'planning';
            $data['currency'] = 'INR';
            $data['public_token'] = Str::random(48);
            $data['created_by'] = Auth::id();

            $shipment = Shipment::create($data);
            $this->addHistory($shipment, $shipment->status, 'Quick shipment created', null);

            return $shipment;
        });

        return redirect()
            ->route('shipments.show', $shipment)
            ->with('success', 'Quick shipment created successfully.');
    }

    public function show(Shipment $shipment): View
    {
        $shipment->load([
            'items', 'histories', 'attachments', 'client', 'project', 'salesInvoice',
            'costs.vendor', 'costs.paidAccount',
        ]);

        return view('shipments.show', $this->formData($shipment));
    }

    public function edit(Shipment $shipment): View
    {
        $shipment->load(['items', 'histories.creator', 'attachments.uploader']);

        return view('shipments.form', $this->formData($shipment));
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $this->validatedData($request, $shipment);
        $this->validatePhotoAttachments($request);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachment_photos'], $data['notify_client'], $data['document_type']);

        /* Marking it delivered without a date records today rather than
           refusing the change, so the operator is never blocked by it. */
        $data = Shipment::withDeliveryDefaults($data, $shipment);

        $this->assertDeliveryDateAllowed(
            $data['drop_date'] ?? null,
            $data['pickup_date'] ?? optional($shipment->pickup_date)->toDateString()
        );

        $this->assertStatusChangeAllowed($shipment, $data['status'], [
            'remarks' => $request->input('delay_reason') ?: $request->input('remarks'),
        ]);

        DB::transaction(function () use ($request, $shipment, $data, $items) {
            $oldStatus = $shipment->status;
            $data['show_client_portal'] = $request->boolean('show_client_portal');
            $shipment->update($data);
            $this->syncItems($shipment, $items);
            $this->storePhotoAttachments($request, $shipment, $request->boolean('is_public'));

            if ($oldStatus !== $shipment->status) {
                $this->addHistory($shipment, $shipment->status, 'Status changed from '.Shipment::statusOptions()[$oldStatus].' to '.$shipment->statusLabel(), null);
                $this->notifyClientOfStatus($shipment, $oldStatus, $request->boolean('notify_client', true));
            }
        });

        $redirect = redirect()
            ->route('shipments.show', $shipment)
            ->with('success', 'Shipment updated successfully.');

        // Soft paperwork gate: a delivery may still go through, but the office
        // is told exactly which mandatory documents are missing for the file.
        if ($shipment->status === Shipment::STATUS_DELIVERED) {
            $paperwork = app(ShipmentDocuments::class)->summary($shipment);

            if (! $paperwork['complete']) {
                $redirect->with(
                    'warning',
                    'Marked delivered, but paperwork is incomplete — missing '.implode(', ', $paperwork['missing']).'.'
                );
            }
        }

        return $redirect;
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        foreach ($shipment->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $shipment->delete();

        return redirect()
            ->route('shipments.index')
            ->with('success', 'Shipment deleted successfully.');
    }

    public function storeHistory(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $this->validatedHistoryData($request);

        /* The delivery date belongs to the shipment, not to the history row, so
           it is validated on its own: the history form offers the field, an
           empty one means today, and nothing lands on the history table. */
        $deliveryDate = $request->validate([
            'drop_date' => ['nullable', 'date'],
        ])['drop_date'] ?? null;

        /* the field is only about becoming delivered: a date left over from
           another status is ignored rather than silently recorded */
        if ($data['status'] !== Shipment::STATUS_DELIVERED) {
            $deliveryDate = null;
        }

        $this->assertDeliveryDateAllowed($deliveryDate, optional($shipment->pickup_date)->toDateString());

        $data['event_time'] = $data['event_time'] ?? now();
        $data['is_public'] = $request->boolean('is_public', true);
        $data['created_by'] = Auth::id();

        /* Marking it delivered records the day it happened: no date given means
           today, not a validation error. */
        $attributes = ['status' => $data['status']];

        if (filled($deliveryDate)) {
            $attributes['drop_date'] = $deliveryDate;
        }

        $attributes = Shipment::withDeliveryDefaults($attributes, $shipment);
        $recordedDate = $attributes['drop_date'] ?? null;
        $wasDefaulted = ! filled($deliveryDate) && $recordedDate !== null;

        $this->assertStatusChangeAllowed($shipment, $data['status'], $attributes + [
            'remarks' => $data['remarks'] ?? null,
        ]);

        $oldStatus = $shipment->status;
        $notify = $request->boolean('notify_client', true);

        DB::transaction(function () use ($shipment, $data, $attributes, $oldStatus, $notify) {
            $shipment->histories()->create($data);
            $shipment->update($attributes);

            if ($oldStatus !== $shipment->status) {
                $this->notifyClientOfStatus($shipment, $oldStatus, $notify);
            }
        });

        $message = 'Tracking history added successfully.';

        if ($wasDefaulted) {
            $message .= ' The delivery date was recorded as '
                .Carbon::parse($recordedDate)->format('d M Y')
                .' because none was given — edit the shipment to correct it.';
        }

        return back()->with('success', $message);
    }

    /**
     * A delivery date is *not* enforced here — a status change to delivered
     * fills today's date in instead (Shipment::withDeliveryDefaults), so the
     * operator is never blocked by a field they did not fill. What is still
     * refused is a hold or a delay with no explanation: that is noise in every
     * report downstream.
     *
     * @param  array<string, mixed>  $context
     */
    private function assertStatusChangeAllowed(Shipment $shipment, string $status, array $context): void
    {
        if ($status === $shipment->status) {
            return;
        }

        if (in_array($status, [Shipment::STATUS_CUSTOM_HOLD, Shipment::STATUS_DELAYED], true)
            && trim((string) ($context['remarks'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Add a remark explaining why the shipment is on hold or delayed.',
            ]);
        }
    }

    /**
     * Tell the client's portal users that their shipment moved. Only for
     * shipments the client can already see (portal visibility switched on).
     */
    private function notifyClientOfStatus(Shipment $shipment, string $from, bool $requested = true): void
    {
        if (! $requested || ! $shipment->client_id || ! $shipment->show_client_portal) {
            return;
        }

        $label = $shipment->statusLabel();
        $url = route('shipments.publicTrack', $shipment->public_token);

        app(ClientPortalNotifier::class)->notifyClient(
            (int) $shipment->client_id,
            'Shipment '.$shipment->shipment_number.' is now '.$label,
            trim(($shipment->delay_reason ? $shipment->delay_reason."\n" : '')
                .'Previously '.((Shipment::statusOptions()[$from] ?? $from)).'. Track live status: '.$url),
            'shipment',
            'shipment',
            (int) $shipment->id,
            $url
        );
    }

    public function updateHistory(Request $request, ShipmentTrackingHistory $history): RedirectResponse
    {
        $data = $this->validatedHistoryData($request);
        $data['event_time'] = $data['event_time'] ?? $history->event_time ?? now();
        $data['is_public'] = $request->boolean('is_public');

        DB::transaction(function () use ($history, $data) {
            $history->update($data);
            $this->syncShipmentStatusFromLatestHistory($history->shipment);
        });

        return back()->with('success', 'Tracking history updated successfully.');
    }

    public function destroyHistory(ShipmentTrackingHistory $history): RedirectResponse
    {
        DB::transaction(function () use ($history) {
            $shipment = $history->shipment;
            $history->delete();
            $this->syncShipmentStatusFromLatestHistory($shipment);
        });

        return back()->with('success', 'Tracking history deleted successfully.');
    }

    public function storeAttachment(Request $request, Shipment $shipment): RedirectResponse
    {
        $request->validate([
            'attachment_photos' => ['required', 'array'],
            'attachment_photos.*' => ['required', 'file', 'max:8192'],
            'is_public' => ['nullable', 'boolean'],
            'document_type' => ['nullable', 'string', 'max:40'],
        ]);
        $this->validatePhotoAttachments($request);

        $this->storePhotoAttachments($request, $shipment, $request->boolean('is_public'));

        return back()->with('success', 'Shipment photo attachment uploaded successfully.');
    }

    public function updateAttachment(Request $request, ShipmentAttachment $attachment): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'document_type' => ['nullable', 'string', 'max:40'],
        ]);

        $attachment->update([
            'title' => $data['title'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_public' => $request->boolean('is_public'),
            'document_type' => $data['document_type'] ?? $attachment->document_type,
        ]);

        return back()->with('success', 'Shipment photo attachment updated successfully.');
    }

    public function destroyAttachment(ShipmentAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('success', 'Shipment photo attachment deleted successfully.');
    }
    
    public function togglePublic(Request $request, ShipmentAttachment $attachment)
    {
        $attachment->update([
            'is_public' => $request->boolean('is_public')
        ]);
    
        return response()->json([
            'success' => true,
            'is_public' => $attachment->is_public
        ]);
    }

    private function validatedHistoryData(Request $request): array
    {
        return $request->validate([
            'status' => ['required', 'in:planning,picked_up,in_transit,custom_hold,delayed,out_for_delivery,delivered,cancelled'],
            'location' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'event_time' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * A date the operator typed has to make sense: a delivery cannot happen
     * before the pickup it followed. An empty one is fine — it becomes today.
     */
    private function assertDeliveryDateAllowed(?string $deliveryDate, ?string $pickupDate): void
    {
        if (! filled($deliveryDate) || ! filled($pickupDate)) {
            return;
        }

        if (Carbon::parse($deliveryDate)->lt(Carbon::parse($pickupDate))) {
            throw ValidationException::withMessages([
                'drop_date' => 'The delivery date cannot be before the pickup date.',
            ]);
        }
    }

    private function syncShipmentStatusFromLatestHistory(?Shipment $shipment): void
    {
        if (! $shipment) {
            return;
        }

        $latestHistory = $shipment->histories()->first();

        if ($latestHistory) {
            /* the status is derived from history here, so the delivery default
               has to follow it: a history edit can make the row delivered */
            $shipment->update(Shipment::withDeliveryDefaults(
                ['status' => $latestHistory->status],
                $shipment
            ));
        }
    }

    public function publicTrack(string $token): View
    {
        $shipment = Shipment::query()
            ->where('public_token', $token)
            ->with([
                'items',
                'publicAttachments',
                'histories' => fn ($q) => $q->where('is_public', true)->latest('event_time')->latest('id'),
            ])
            ->firstOrFail();

        return view('shipments.public', [
            'shipment' => $shipment,
            'statusOptions' => Shipment::statusOptions(),
        ]);
    }

    private function validatedData(Request $request, ?Shipment $shipment = null): array
    {
        return $request->validate([
            'shipment_number' => ['nullable', 'string', 'max:255', 'unique:shipments,shipment_number,'.($shipment?->id ?? 'NULL')],
            'identity_name' => ['required', 'string', 'max:255'],
            'shipment_label' => ['nullable', 'string', 'max:255'],
            'shipment_type' => ['required', 'in:domestic,import,export'],
            'shipment_mode' => ['nullable', 'in:courier,air,sea,road,rail'],
            'pickup_date' => ['nullable', 'date'],
            'drop_date' => ['nullable', 'date', 'after_or_equal:pickup_date'],
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'show_client_portal' => ['nullable', 'boolean'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'string'],
            'from_city' => ['nullable', 'string', 'max:255'],
            'from_state' => ['nullable', 'string', 'max:255'],
            'from_country' => ['nullable', 'string', 'max:255'],
            'from_pincode' => ['nullable', 'string', 'max:30'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_mobile' => ['nullable', 'string', 'max:40'],

            'to_name' => ['nullable', 'string', 'max:255'],
            'to_address' => ['nullable', 'string'],
            'to_city' => ['nullable', 'string', 'max:255'],
            'to_state' => ['nullable', 'string', 'max:255'],
            'to_country' => ['nullable', 'string', 'max:255'],
            'to_pincode' => ['nullable', 'string', 'max:30'],
            'to_email' => ['nullable', 'email', 'max:255'],
            'to_mobile' => ['nullable', 'string', 'max:40'],

            'logistic_partner' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'bill_of_entry_number' => ['nullable', 'string', 'max:255'],
            'eway_bill_number' => ['nullable', 'string', 'max:40'],
            'eway_bill_valid_until' => ['nullable', 'date'],
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],

            'status' => ['required', 'in:planning,picked_up,in_transit,custom_hold,delayed,out_for_delivery,delivered,cancelled'],
            'eta_date' => ['nullable', 'date'],
            'delay_reason' => ['nullable', 'string', 'max:60'],
            'sales_invoice_id' => ['nullable', 'integer'],
            'notify_client' => ['nullable', 'boolean'],
            'document_type' => ['nullable', 'string', 'max:40'],
            'shipment_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:INR,RMB,USD'],
            'cost_borne_by' => ['required', 'in:shipper,receiver,misspack'],
            'package_count' => ['nullable', 'integer', 'min:0'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'chargeable_weight' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'attachment_photos' => ['nullable', 'array'],
            'attachment_photos.*' => ['nullable', 'file', 'max:8192'],

            'items' => ['nullable', 'array'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.hs_code' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.declared_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'in:INR,RMB,USD'],
            'items.*.net_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string'],
        ]);
    }

    private function validatePhotoAttachments(Request $request): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'xls', 'xlsx', 'pdf', 'doc'];

        foreach ((array) $request->file('attachment_photos', []) as $index => $file) {
            if (! $file) {
                continue;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (! in_array($extension, $allowedExtensions, true)) {
                throw ValidationException::withMessages([
                    'attachment_photos.'.($index + 1) => 'Only JPG, JPEG, PNG, WEBP, GIF, HEIC and HEIF shipment photos are allowed. If documents then it should be XLS, XLSX, PDF and DOC file.',
                ]);
            }
        }
    }

    private function storePhotoAttachments(Request $request, Shipment $shipment, ?bool $forcePublic = null): void
    {
        $documentType = $request->input('document_type');

        foreach ((array) $request->file('attachment_photos', []) as $file) {
            if (! $file) {
                continue;
            }

            $mime = (string) $file->getMimeType();

            $shipment->attachments()->create([
                // A document type makes it paperwork; anything else is stamped
                // by what the file actually is (the old code called every
                // upload a photo, including PDFs).
                'attachment_type' => $documentType
                    ? 'document'
                    : (str_starts_with($mime, 'image/') ? 'photo' : 'document'),
                'document_type' => $documentType ?: null,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $file->store('shipments/photos', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => $file->getSize(),
                'is_public' => $forcePublic ?? false,
                'sort_order' => 0,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function syncItems(Shipment $shipment, array $items): void
    {
        $shipment->items()->delete();

        foreach ($items as $item) {
            if (blank($item['product_name'] ?? null)) {
                continue;
            }

            $shipment->items()->create([
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?? null,
                'hs_code' => $item['hs_code'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'unit' => $item['unit'] ?? 'pcs',
                'declared_value' => $item['declared_value'] ?? null,
                'currency' => $item['currency'] ?? $shipment->currency,
                'net_weight' => $item['net_weight'] ?? null,
                'gross_weight' => $item['gross_weight'] ?? null,
                'description' => $item['description'] ?? null,
            ]);
        }
    }

    private function addHistory(Shipment $shipment, string $status, ?string $remarks = null, ?string $location = null): ShipmentTrackingHistory
    {
        return $shipment->histories()->create([
            'status' => $status,
            'location' => $location,
            'remarks' => $remarks,
            'event_time' => now(),
            'is_public' => true,
            'created_by' => Auth::id(),
        ]);
    }

    private function makeShipmentNumber(): string
    {
        $prefix = 'SHIP-';
        $next = str_pad((string) (Shipment::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (Shipment::where('shipment_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    /**
     * Printable shipping marks (carton stickers) for a shipment.
     *
     * One mark per package by default — a 10-package shipment prints 10
     * stickers — and the operator can override the count with ?copies=N.
     */
    public function shippingMark(Request $request, Shipment $shipment): View
    {
        $default = max(1, (int) ($shipment->package_count ?: 1));
        $copies = (int) $request->query('copies', $default);
        $copies = max(1, min(48, $copies ?: 1));

        return view('shipments.shipping-mark', [
            'shipment' => $shipment,
            'copies' => $copies,
            /* the label is 85 × 130 mm: one sticker per label */
            'perPage' => 1,
            'modeOptions' => Shipment::modeOptions(),
        ]);
    }

    /**
     * From / To memory for the shipment form.
     *
     * GET /shipments/party-lookup?field=from&name=MissPack
     *   → the best known contact + address block for that name, plus the list
     *     of party names already used (for the datalist).
     */
    public function partyLookup(Request $request, ShipmentPartyDirectory $directory): JsonResponse
    {
        $field = $directory->normaliseField($request->query('field'));

        return response()->json($directory->payload($field, $request->query('name')));
    }

    /**
     * From / To names already used, for the form and quick-create datalists.
     */
    /**
     * Save the filter set currently on screen as a named view.
     */
    public function storeSavedView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        app(SavedViews::class)->save(Auth::id(), 'shipments', $data['name'], $request->query(), $request->boolean('is_shared'));

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(SavedView $savedView): RedirectResponse
    {
        abort_unless((int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete((int) Auth::id(), (int) $savedView->id);

        return back()->with('success', 'Saved view removed.');
    }

    /**
     * Add a cost head (freight, duty, CHA…) to a shipment. Paid heads post an
     * INR cashflow entry through the same ledger the rest of the ERP uses.
     */
    public function storeCost(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $this->validatedCostData($request);

        $cost = new ShipmentCost($data);
        $cost->shipment_id = $shipment->id;
        $cost->created_by = Auth::id();
        $cost->sort_order = (int) $shipment->costs()->max('sort_order') + 1;

        app(ShipmentCostLedger::class)->recalculateInr($cost);

        DB::transaction(function () use ($cost) {
            $cost->save();
            app(ShipmentCostCashflowSync::class)->sync($cost, $cost->isPaid());
        });

        return back()->with(
            'success',
            $cost->isPaid()
                ? 'Cost added and posted to the cashflow ledger.'
                : 'Cost added. Set the paid account when it is settled to post it to cashflow.'
        );
    }

    public function updateCost(Request $request, ShipmentCost $cost): RedirectResponse
    {
        $data = $this->validatedCostData($request);

        $cost->fill($data);
        app(ShipmentCostLedger::class)->recalculateInr($cost);

        DB::transaction(function () use ($cost) {
            $cost->save();
            app(ShipmentCostCashflowSync::class)->sync($cost, $cost->isPaid());
        });

        return back()->with('success', 'Cost updated.');
    }

    public function destroyCost(ShipmentCost $cost): RedirectResponse
    {
        DB::transaction(function () use ($cost) {
            app(ShipmentCostCashflowSync::class)->remove($cost);
            $cost->delete();
        });

        return back()->with('success', 'Cost removed.');
    }

    /**
     * Print-ready paperwork built from the shipment itself: packing list,
     * delivery challan or a one-page summary. Downloads a PDF when the dompdf
     * package is present, otherwise renders the same page for Print > Save as
     * PDF — the pattern already used by the cashflow reports.
     */
    /**
     * One sticker per open shipment on a single printable sheet — the "print
     * the board" button warehouses actually use, instead of opening each
     * shipment's shipping mark one by one.
     */
    public function stickers(Request $request): View
    {
        $copies = max(1, min(8, (int) $request->query('copies', 1)));
        $status = (string) $request->query('status', '');

        $shipments = Shipment::query()
            ->open()
            ->when(
                $status !== '' && array_key_exists($status, Shipment::statusOptions()),
                fn ($query) => $query->where('status', $status)
            )
            ->priorityOrder()
            ->limit(self::STICKER_SHEET_LIMIT)
            ->get();

        return view('shipments.stickers', [
            'shipments' => $shipments,
            'copies' => $copies,
            'status' => $status,
            'statusOptions' => Shipment::statusOptions(),
            'modeOptions' => Shipment::modeOptions(),
            'limit' => self::STICKER_SHEET_LIMIT,
            'perPage' => 1,
        ]);
    }

    public function printPack(Request $request, Shipment $shipment, string $document)
    {
        $views = [
            'packing-list' => 'shipments.print.packing-list',
            'delivery-challan' => 'shipments.print.delivery-challan',
            'summary' => 'shipments.print.summary',
        ];

        abort_unless(isset($views[$document]), 404);

        $shipment->load(['items', 'costs.vendor', 'attachments', 'client', 'project', 'salesInvoice', 'histories']);

        $documents = app(ShipmentDocuments::class);

        $viewData = [
            'shipment' => $shipment,
            'items' => $shipment->items,
            'costSummary' => $shipment->costSummary(),
            'checklist' => $documents->checklist($shipment),
            'documentSummary' => $documents->summary($shipment),
            'costHeads' => ShipmentCost::headOptions(),
            'statusOptions' => Shipment::statusOptions(),
            'modeOptions' => Shipment::modeOptions(),
            'printMode' => true,
        ];

        $fileName = $document.'-'.$shipment->shipment_number.'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($views[$document], $viewData)
                ->setPaper('a4')
                ->download($fileName);
        }

        return response()->view($views[$document], $viewData + [
            'pdfFallbackMessage' => "Install barryvdh/laravel-dompdf for a direct PDF download. Use your browser's Print > Save as PDF for now.",
        ]);
    }

    private function validatedCostData(Request $request): array
    {
        $data = $request->validate([
            'cost_head' => ['required', 'in:'.implode(',', array_keys(ShipmentCost::headOptions()))],
            'label' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'vendor_id' => ['nullable', 'integer'],
            'incurred_on' => ['nullable', 'date'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'paid_account_id' => ['nullable', 'integer'],
            'payment_mode' => ['nullable', 'string', 'max:40'],
            'paid_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['currency'] = strtoupper($data['currency']);

        if ($data['currency'] !== 'INR' && (float) ($data['exchange_rate'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Enter the exchange rate this bill was raised at, so the INR value is right.',
            ]);
        }

        if (! empty($data['paid_account_id'])) {
            $data['paid_on'] = $data['paid_on'] ?? ($data['incurred_on'] ?? now()->toDateString());
            $data['payment_mode'] = CashflowEntry::normalisePaymentMode($data['payment_mode'] ?? null) ?: 'neft';
        } else {
            // Not settled yet: no cash moved, so nothing must look like a payment.
            $data['paid_on'] = null;
            $data['payment_mode'] = null;
        }

        return $data;
    }

    /**
     * The exchange rate each currency was last billed at, newest wins: the
     * shipment's own history first, then the rest of the book. The modal uses
     * it as the starting value for a foreign cost — the operator confirms a
     * number instead of inventing one, and a blank rate is never the reason a
     * freight bill cannot be entered.
     *
     * @return array<string, array{rate: string, on: string|null}>
     */
    private function lastCostRates(Shipment $shipment): array
    {
        $rates = [];

        $collect = function ($costs) use (&$rates) {
            foreach ($costs as $cost) {
                $currency = strtoupper((string) $cost->currency);

                if ($currency === 'INR' || (float) $cost->exchange_rate <= 0) {
                    continue;
                }

                $rates[$currency] = [
                    'rate' => rtrim(rtrim(number_format((float) $cost->exchange_rate, 6, '.', ''), '0'), '.'),
                    'on' => optional($cost->incurred_on)->format('d M Y') ?: optional($cost->created_at)->format('d M Y'),
                ];
            }
        };

        $columns = ['currency', 'exchange_rate', 'incurred_on', 'created_at'];

        /* oldest first, so the newest rate per currency is the one that stays */
        $collect(ShipmentCost::query()->orderBy('id')->get($columns));
        $collect($shipment->costs()->orderBy('id')->get($columns));

        return $rates;
    }

    /**
     * Sales invoices this shipment can be measured against (its client's).
     */
    private function invoiceOptions(Shipment $shipment)
    {
        if (! class_exists(SalesInvoice::class)) {
            return collect();
        }

        return SalesInvoice::query()
            ->when($shipment->client_id, fn ($query) => $query->where('client_id', $shipment->client_id))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'invoice_number', 'total_amount', 'currency', 'invoice_date', 'status']);
    }

    private function paidAccounts()
    {
        if (! class_exists(CashflowAccount::class)) {
            return collect();
        }

        return CashflowAccount::query()->orderBy('account_name')->get(['id', 'account_name', 'account_type']);
    }

    private function partyNames(): array
    {
        $directory = app(ShipmentPartyDirectory::class);

        return [
            'from' => $directory->names('from'),
            'to' => $directory->names('to'),
        ];
    }

    /**
     * The list query shared by the index, its counters and its money totals,
     * so a new filter only has to be added in one place.
     */
    /**
     * When the request carries ?saved_view=ID, the saved query is what should
     * be rendered — this turns it back into the URL the list already speaks.
     *
     * @return array<string, string>
     */
    private function resolveSavedView(Request $request): array
    {
        $id = (int) $request->query('saved_view', 0);

        if (! $id) {
            return [];
        }

        $view = SavedView::query()
            ->where('module', 'shipments')
            ->where(function ($query) {
                $query->where('user_id', Auth::id())->orWhere('is_shared', true);
            })
            ->find($id);

        return $view ? app(SavedViews::class)->queryFor($view) : [];
    }

    private function filteredQuery(Request $request, bool $withAttention = true)
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $currency = $request->query('currency', 'all');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        return Shipment::query()
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('shipment_type', $type))
            ->when($currency !== 'all', fn ($q) => $q->where('currency', $currency))
            ->when($fromDate, fn ($q) => $q->whereDate('pickup_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('pickup_date', '<=', $toDate))
            ->when($withAttention, fn ($q) => $q->attention($request->query('attention')));
    }

    /**
     * How many shipments sit behind each "needs attention" chip, using the
     * filters already on screen — so the counts never lie about the list.
     *
     * @return array<string, int>
     */
    private function attentionCounts(Request $request): array
    {
        $counts = [];

        foreach (['needs_attention', 'overdue', 'due_soon', 'hold', 'docs_pending', 'eway_expiring'] as $type) {
            $counts[$type] = $this->filteredQuery($request, false)->attention($type)->count();
        }

        return $counts;
    }

    /**
     * Money spent as per the entries currently being listed: cost heads in the
     * currency they were billed in, plus the legacy single figure for
     * shipments that have no breakdown yet.
     *
     * @return array<string, float>
     */
    private function spendByCurrency(Request $request): array
    {
        $totals = [];

        $shipmentIds = $this->filteredQuery($request)->select('id');

        ShipmentCost::query()
            ->whereIn('shipment_id', $shipmentIds)
            ->selectRaw("COALESCE(NULLIF(currency, ''), 'INR') as currency, SUM(amount) as total")
            ->groupByRaw("COALESCE(NULLIF(currency, ''), 'INR')")
            ->pluck('total', 'currency')
            ->each(function ($total, $currency) use (&$totals) {
                $totals[$currency] = round(($totals[$currency] ?? 0) + (float) $total, 2);
            });

        $this->filteredQuery($request)
            ->whereDoesntHave('costs')
            ->whereNotNull('shipment_cost')
            ->selectRaw("COALESCE(NULLIF(currency, ''), 'INR') as currency, SUM(shipment_cost) as total")
            ->groupByRaw("COALESCE(NULLIF(currency, ''), 'INR')")
            ->pluck('total', 'currency')
            ->each(function ($total, $currency) use (&$totals) {
                $totals[$currency] = round(($totals[$currency] ?? 0) + (float) $total, 2);
            });

        return $totals;
    }

    private function formData(Shipment $shipment): array
    {
        return [
            'shipment' => $shipment,
            'statusOptions' => Shipment::statusOptions(),
            'typeOptions' => Shipment::typeOptions(),
            'currencyOptions' => Shipment::currencyOptions(),
            'costBorneByOptions' => Shipment::costBorneByOptions(),
            'modeOptions' => Shipment::modeOptions(),
            // From / To memory: names already used, so the form can suggest
            // and prefill them without another round trip on page load.
            'partyNames' => $this->partyNames(),
            'documentTypes' => ShipmentDocuments::labels(),
            'documentChecklist' => app(ShipmentDocuments::class)->checklist($shipment),
            'documentSummary' => app(ShipmentDocuments::class)->summary($shipment),
            'costHeads' => ShipmentCost::headOptions(),
            'costTotals' => $shipment->costSummary(),
            'invoiceOptions' => $this->invoiceOptions($shipment),
            'paymentModeOptions' => CashflowEntry::paymentModeOptions(),
            'paidAccounts' => $this->paidAccounts(),
            /* the rate each currency was last billed at — offered as the
               starting value so a foreign cost is confirmed, not guessed */
            'lastCostRates' => $this->lastCostRates($shipment),
        ];
    }
}
