<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\ShipmentTrackingHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $currency = $request->query('currency', 'all');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $shipmentsQuery = Shipment::query()
            ->with('creator')
            ->withCount('items')
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('shipment_type', $type))
            ->when($currency !== 'all', fn ($q) => $q->where('currency', $currency))
            ->when($fromDate, fn ($q) => $q->whereDate('pickup_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('pickup_date', '<=', $toDate));

        $shipments = $shipmentsQuery
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

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

        return view('shipments.index', [
            'shipments' => $shipments,
            'stats' => $stats,
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
        unset($data['items'], $data['attachment_photos']);

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
        $shipment->load(['items', 'histories.creator', 'attachments.uploader', 'creator']);

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
        unset($data['items'], $data['attachment_photos']);

        DB::transaction(function () use ($request, $shipment, $data, $items) {
            $oldStatus = $shipment->status;
            $data['show_client_portal'] = $request->boolean('show_client_portal');
            $shipment->update($data);
            $this->syncItems($shipment, $items);
            $this->storePhotoAttachments($request, $shipment, $request->boolean('is_public'));

            if ($oldStatus !== $shipment->status) {
                $this->addHistory($shipment, $shipment->status, 'Status changed from '.Shipment::statusOptions()[$oldStatus].' to '.$shipment->statusLabel(), null);
            }
        });

        return redirect()
            ->route('shipments.show', $shipment)
            ->with('success', 'Shipment updated successfully.');
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
        $data['event_time'] = $data['event_time'] ?? now();
        $data['is_public'] = $request->boolean('is_public', true);
        $data['created_by'] = Auth::id();

        DB::transaction(function () use ($shipment, $data) {
            $shipment->histories()->create($data);
            $shipment->update(['status' => $data['status']]);
        });

        return back()->with('success', 'Tracking history added successfully.');
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
        ]);

        $attachment->update([
            'title' => $data['title'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_public' => $request->boolean('is_public'),
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

    private function syncShipmentStatusFromLatestHistory(?Shipment $shipment): void
    {
        if (! $shipment) {
            return;
        }

        $latestHistory = $shipment->histories()->first();

        if ($latestHistory) {
            $shipment->update(['status' => $latestHistory->status]);
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
            'origin_port' => ['nullable', 'string', 'max:255'],
            'destination_port' => ['nullable', 'string', 'max:255'],

            'status' => ['required', 'in:planning,picked_up,in_transit,custom_hold,delayed,out_for_delivery,delivered,cancelled'],
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
        foreach ((array) $request->file('attachment_photos', []) as $file) {
            if (! $file) {
                continue;
            }

            $shipment->attachments()->create([
                'attachment_type' => 'photo',
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $file->store('shipments/photos', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
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

    private function formData(Shipment $shipment): array
    {
        return [
            'shipment' => $shipment,
            'statusOptions' => Shipment::statusOptions(),
            'typeOptions' => Shipment::typeOptions(),
            'currencyOptions' => Shipment::currencyOptions(),
            'costBorneByOptions' => Shipment::costBorneByOptions(),
            'modeOptions' => Shipment::modeOptions(),
        ];
    }
}
