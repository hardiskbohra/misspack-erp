<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\PackagingListSource;
use App\Services\PackagingListProjectLinker;
use App\Services\PackagingListVerifier;
use App\Models\Shipment;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PackagingListController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');
        $mode = (string) $request->query('mode', 'all');

        $base = PackagingList::query()
            ->with(['vendor', 'client', 'project', 'shipment'])
            ->withCount('cartons')
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('packing_number', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('from_name', 'like', "%{$search}%")
                    ->orWhere('bill_to_name', 'like', "%{$search}%")
                    ->orWhere('ship_to_name', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project
                        ->where('project_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            }))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($mode !== 'all', fn ($query) => $query->where('transport_mode', $mode));

        $stats = [
            'total' => PackagingList::count(),
            'draft' => PackagingList::where('status', 'draft')->count(),
            'confirmed' => PackagingList::whereIn('status', ['confirmed', 'dispatched'])->count(),
            'cartons' => (int) PackagingList::sum('total_cartons'),
        ];

        return view('packaging_lists.index', [
            'lists' => $base->latest('packing_date')->latest('id')->paginate(30)->withQueryString(),
            'stats' => $stats, 'search' => $search, 'status' => $status, 'mode' => $mode,
            'statusOptions' => PackagingList::statusOptions(),
            'modeOptions' => PackagingList::transportModeOptions(),
        ]);
    }

    public function create(Request $request, PackagingListSource $sources): View
    {
        $list = new PackagingList([
            'packing_date' => now()->toDateString(), 'status' => 'draft', 'transport_mode' => 'air',
            'declaration' => 'I/We hereby certify that the information of this packaging list is true and correct and that the contents of this shipment are as stated above.',
        ]);
        $list->packing_number = $this->nextNumber();
        $organisation = Organisation::current();
        $address = $organisation->defaultAddress('billing') ?: $organisation->defaultAddress('shipping');
        $list->bill_to_name = $organisation->legal_name ?: $organisation->trade_name;
        $list->bill_to_address = $address?->oneLine();
        $prefillCartons = [];

        $source = null;
        if ($invoice = SalesInvoice::find((int) $request->query('sales_invoice'))) $source = $sources->salesInvoice($invoice);
        elseif ($purchase = PurchaseInvoice::where('invoice_type', PurchaseInvoice::TYPE_BILL)->find((int) $request->query('purchase_invoice'))) $source = $sources->purchaseInvoice($purchase);
        elseif ($shipment = Shipment::find((int) $request->query('shipment'))) $source = $sources->shipment($shipment);

        if ($source) {
            $list->forceFill($source['attributes']);
            $prefillCartons = $source['cartons'];
        }

        return view('packaging_lists.form', $this->formData($list, $prefillCartons));
    }

    public function store(Request $request, PackagingListVerifier $verifier, PackagingListProjectLinker $linker): RedirectResponse
    {
        $data = $this->validated($request);
        $list = DB::transaction(function () use ($data, $verifier, $linker) {
            $cartons = $data['cartons'];
            unset($data['cartons']);
            $data['packing_warnings'] = $this->packingWarnings($cartons);
            $data['created_by'] = Auth::id();
            $data['packing_number'] = $data['packing_number'] ?: $this->nextNumber();
            $list = PackagingList::create($data);
            $this->syncCartons($list, $cartons);
            $linker->reconcile($list);
            $this->assertReadyForStatus($list, $data['status'], $verifier, $cartons);
            return $list;
        });

        $this->refreshProjectReadiness($list);
        return redirect()->route('packaging-lists.show', $list)->with('success', 'Packaging list created successfully.');
    }

    public function show(PackagingList $packagingList, PackagingListVerifier $verifier, PackagingListProjectLinker $linker): View
    {
        $packagingList->load(['cartons.items.product', 'vendor', 'client', 'project', 'shipment', 'salesInvoice', 'purchaseInvoice', 'creator', 'packingOwner']);
        $linker->reconcile($packagingList);
        return view('packaging_lists.show', [
            'list' => $packagingList,
            /* The same product can sit in several carton groups; the screen and
               the printed document read it as one product with one total. */
            'sections' => $packagingList->sections(),
            'verificationInspection' => $verifier->inspect($packagingList),
        ]);
    }

    public function edit(PackagingList $packagingList): View
    {
        $packagingList->load('cartons.items');
        return view('packaging_lists.form', $this->formData($packagingList));
    }

    public function update(Request $request, PackagingList $packagingList, PackagingListVerifier $verifier, PackagingListProjectLinker $linker): RedirectResponse
    {
        $this->assertPackingEditable($packagingList);
        $data = $this->validated($request, $packagingList);
        DB::transaction(function () use ($data, $packagingList, $verifier, $linker) {
            $cartons = $data['cartons'];
            unset($data['cartons']);
            $data['packing_warnings'] = $this->packingWarnings($cartons);
            $packagingList->update($data);
            $packagingList->cartons()->delete();
            $this->syncCartons($packagingList, $cartons);
            $linker->reconcile($packagingList);
            $this->assertReadyForStatus($packagingList->fresh(), $data['status'], $verifier, $cartons);
        });

        $this->refreshProjectReadiness($packagingList->fresh());
        return redirect()->route('packaging-lists.show', $packagingList)->with('success', 'Packaging list updated successfully.');
    }

    public function updateStatus(Request $request, PackagingList $packagingList, PackagingListVerifier $verifier, PackagingListProjectLinker $linker): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(PackagingList::statusOptions()))]]);
        if ($data['status'] !== $packagingList->status && $data['status'] !== 'dispatched') $this->assertPackingEditable($packagingList);
        $linker->reconcile($packagingList);
        $this->assertReadyForStatus($packagingList, $data['status'], $verifier);
        $packagingList->update(['status' => $data['status']]);
        $this->refreshProjectReadiness($packagingList);

        return back()->with('success', 'Packaging list status changed to '.$packagingList->statusLabel().'.');
    }

    public function destroy(PackagingList $packagingList): RedirectResponse
    {
        $this->assertPackingEditable($packagingList);
        $packagingList->loadMissing('project.workflow');
        $packagingList->delete();
        $this->refreshProjectReadiness($packagingList);
        return redirect()->route('packaging-lists.index')->with('success', 'Packaging list deleted successfully.');
    }

    public function print(PackagingList $packagingList): View
    {
        $packagingList->load(['cartons.items.product', 'vendor', 'client', 'project', 'shipment']);
        $organisation = Organisation::current();

        return view('packaging_lists.print', [
            'list' => $packagingList,
            'sections' => $packagingList->sections(),
            'organisation' => $organisation,
            /* Our logo goes on our own letterhead only. A sheet drawn from a
               vendor's bill is the vendor's document and prints without it. */
            'onLetterhead' => $packagingList->issuedByUs($organisation),
        ]);
    }

    public function updatePrintSettings(Request $request, PackagingList $packagingList): RedirectResponse
    {
        // Presentation only: never recreate cartons, reverify packing, or change workflow state.
        $settings = $request->validate([
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'header_text' => ['nullable', 'string', 'max:160'],
            'footer_text' => ['nullable', 'string', 'max:240'],
            'show_page_numbers' => ['required', 'boolean'],
        ]);
        $settings['header_text'] = $settings['header_text'] ?? '';
        $settings['footer_text'] = $settings['footer_text'] ?? '';
        $settings['show_page_numbers'] = $request->boolean('show_page_numbers');
        $packagingList->update(['print_settings' => $settings]);

        return redirect()->route('packaging-lists.print', $packagingList)->with('success', 'Print settings saved for this packaging list.');
    }

    public function duplicate(PackagingList $packagingList): RedirectResponse
    {
        $copy = DB::transaction(function () use ($packagingList) {
            $packagingList->load('cartons.items');
            $copy = $packagingList->replicate(['total_cartons', 'total_net_weight', 'total_gross_weight', 'total_cbm']);
            $copy->packing_number = $this->nextNumber();
            $copy->packing_date = now();
            $copy->status = 'draft';
            $copy->created_by = Auth::id();
            $copy->save();
            foreach ($packagingList->cartons as $carton) {
                $newCarton = $copy->cartons()->create($carton->only(array_diff($carton->getFillable(), ['packaging_list_id','barcode','public_token'])));
                $newCarton->items()->createMany($carton->items->map(fn ($item) => $item->only(array_diff($item->getFillable(), ['packaging_list_carton_id'])))->all());
            }
            $copy->recalculate();
            return $copy;
        });

        return redirect()->route('packaging-lists.edit', $copy)->with('success', 'Packaging list duplicated. Review it before confirming.');
    }

    private function validated(Request $request, ?PackagingList $list = null): array
    {
        $data = $request->validate([
            'packing_number' => ['required', 'string', 'max:60', Rule::unique('packaging_lists')->ignore($list), Rule::when($list === null || $request->input('packing_number') !== $list->packing_number, ['regex:/^MP\/PL\/\d{2}-\d{2}\/\d{3,}$/'])],
            'packing_date' => ['required', 'date'], 'invoice_number' => ['nullable', 'string', 'max:100'],
            'transport_mode' => ['required', Rule::in(array_keys(PackagingList::transportModeOptions()))],
            'status' => ['required', Rule::in(array_keys(PackagingList::statusOptions()))],
            'source_type' => ['nullable', Rule::in(['sales_invoice','purchase_invoice','shipment'])],
            'source_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'exists:vendors,id'], 'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'], 'shipment_id' => ['nullable', 'exists:shipments,id'],
            'sales_invoice_id' => ['nullable', 'exists:sales_invoices,id'],
            'purchase_invoice_id' => ['nullable', 'exists:purchase_invoices,id'],
            'from_name' => ['required', 'string', 'max:255'], 'from_address' => ['nullable', 'string'],
            'from_contact' => ['nullable', 'string', 'max:255'], 'from_email' => ['nullable', 'email', 'max:255'],
            'bill_to_name' => ['required', 'string', 'max:255'], 'bill_to_address' => ['nullable', 'string'],
            'ship_to_name' => ['required', 'string', 'max:255'], 'ship_to_address' => ['nullable', 'string'],
            'print_columns' => ['sometimes', 'required', 'array', 'min:1', 'max:12'],
            'print_columns.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PackagingList::printColumnOptions()))],
            'declaration' => ['nullable', 'string'], 'customer_signature' => ['nullable', 'string', 'max:255'],
            'supplier_signature' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'],
            'cartons' => ['required', 'array', 'min:1', 'max:200'],
            'cartons.*.carton_label' => ['nullable', 'string', 'max:100'],
            'cartons.*.packing_group' => ['nullable', 'string', 'max:120'],
            'cartons.*.packing_mode' => ['required', Rule::in(['grouped','individual'])],
            'cartons.*.carton_number' => ['nullable', 'string', 'max:100'],
            'cartons.*.carton_type' => ['required', 'string', 'max:40'],
            'cartons.*.shipping_mark' => ['nullable', 'string', 'max:255'],
            'cartons.*.material' => ['nullable', 'string', 'max:255'],
            'cartons.*.pallet_number' => ['nullable', 'string', 'max:100'],
            'cartons.*.seal_number' => ['nullable', 'string', 'max:100'],
            'cartons.*.carton_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'cartons.*.length_cm' => ['required', 'numeric', 'gt:0'], 'cartons.*.width_cm' => ['required', 'numeric', 'gt:0'],
            'cartons.*.height_cm' => ['required', 'numeric', 'gt:0'],
            'cartons.*.net_weight_per_carton' => ['required', 'numeric', 'min:0'],
            'cartons.*.gross_weight_per_carton' => ['required', 'numeric', 'min:0', 'gte:cartons.*.net_weight_per_carton'],
            'cartons.*.tare_weight' => ['nullable', 'numeric', 'min:0'],
            'cartons.*.is_fragile' => ['nullable', 'boolean'], 'cartons.*.keep_dry' => ['nullable', 'boolean'],
            'cartons.*.this_side_up' => ['nullable', 'boolean'], 'cartons.*.is_stackable' => ['nullable', 'boolean'],
            'cartons.*.is_hazardous' => ['nullable', 'boolean'], 'cartons.*.handling_instructions' => ['nullable', 'string'],
            'cartons.*.items' => ['required', 'array', 'min:1', 'max:200'],
            'cartons.*.items.*.product_id' => ['nullable', 'exists:products,id'],
            'cartons.*.items.*.project_product_id' => ['nullable', Rule::exists('project_products', 'id')->where(fn ($query) => $query->where('project_id', $request->input('project_id'))->where('status', '!=', 'cancelled'))],
            'cartons.*.items.*.source_type' => ['nullable', Rule::in(['sales_invoice','purchase_invoice','shipment'])],
            'cartons.*.items.*.source_item_id' => ['nullable', 'integer'],
            'cartons.*.items.*.source_quantity' => ['nullable', 'numeric', 'min:0'],
            'cartons.*.items.*.previously_packed_quantity' => ['nullable', 'numeric', 'min:0'],
            'cartons.*.items.*.item_description' => ['required', 'string', 'max:500'],
            'cartons.*.items.*.quantity_per_carton' => ['required', 'numeric', 'min:0'],
            'cartons.*.items.*.total_quantity' => ['required', 'numeric', 'min:0'],
            'cartons.*.items.*.unit' => ['required', 'string', 'max:30'],
            'cartons.*.items.*.sku' => ['nullable', 'string', 'max:255'],
            'cartons.*.items.*.customer_item_code' => ['nullable', 'string', 'max:255'],
            'cartons.*.items.*.hs_code' => ['nullable', 'string', 'max:40'],
            'cartons.*.items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'cartons.*.items.*.serial_numbers_text' => ['nullable', 'string'],
            'cartons.*.items.*.manufactured_at' => ['nullable', 'date'],
            'cartons.*.items.*.expires_at' => ['nullable', 'date', 'after_or_equal:cartons.*.items.*.manufactured_at'],
            'cartons.*.items.*.country_of_origin' => ['nullable', 'string', 'max:255'],
            'cartons.*.items.*.unit_net_weight' => ['nullable', 'numeric', 'min:0'],
            'cartons.*.items.*.unit_gross_weight' => ['nullable', 'numeric', 'min:0'],
            'cartons.*.items.*.packing_instructions' => ['nullable', 'string'],
            'cartons.*.items.*.remarks' => ['nullable', 'string'],
        ]);
        $serials = [];
        foreach ($data['cartons'] as $cartonIndex => &$carton) {
            if (($carton['packing_mode'] ?? 'grouped') === 'individual') $carton['carton_count'] = 1;
            $carton['carton_number'] = (string) ($cartonIndex + 1);
            foreach ($carton['items'] as $itemIndex => $item) foreach (preg_split('/[\r\n,]+/', (string)($item['serial_numbers_text'] ?? '')) as $serial) {
                $serial = strtoupper(trim($serial)); if ($serial === '') continue;
                if (isset($serials[$serial])) throw ValidationException::withMessages(["cartons.$cartonIndex.items.$itemIndex.serial_numbers_text" => "Serial number $serial appears more than once in this packaging list."]);
                $serials[$serial] = true;
            }
        }
        unset($carton);
        return $data;
    }

    private function syncCartons(PackagingList $list, array $cartons): void
    {
        foreach (array_values($cartons) as $cartonIndex => $row) {
            $items = $row['items']; unset($row['items']);
            foreach (['is_fragile','keep_dry','this_side_up','is_stackable','is_hazardous'] as $flag) $row[$flag] = (bool) ($row[$flag] ?? false);
            $row['tare_weight'] = (float) ($row['tare_weight'] ?? 0);
            $row['sort_order'] = $cartonIndex;
            $carton = $list->cartons()->create($row);
            foreach (array_values($items) as $itemIndex => $item) {
                // Total quantity is derived, never trusted from a stale or edited browser value.
                $item['total_quantity'] = round((float) $item['quantity_per_carton'] * (int) $carton->carton_count, 3);
                $item['previously_packed_quantity'] = (float) ($item['previously_packed_quantity'] ?? 0);
                $serialText = (string) ($item['serial_numbers_text'] ?? '');
                unset($item['serial_numbers_text']);
                $item['serial_numbers'] = collect(preg_split('/[\r\n,]+/', $serialText))->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
                $item['sort_order'] = $itemIndex;
                $carton->items()->create($item);
            }
        }
        $list->recalculate();
    }

    public function labels(PackagingList $packagingList, Request $request, \App\Services\PackagingListLabels $labels): View
    {
        $data = $request->validate([
            'format' => ['required', Rule::in(['a4','a5','thermal','thermal150'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $labelBatches = $labels->batches($packagingList);
        $copies = array_sum(array_column($labelBatches, 'copies'));
        // Use the loaded physical count for every n/N caption, including legacy lists.
        $packagingList->total_cartons = $copies;
        DB::transaction(function () use ($labelBatches, $data) {
            foreach ($labelBatches as $batch) {
                $batch['carton']->labelPrints()->create([
                    'label_format' => $data['format'], 'copies' => $batch['copies'],
                    'reason' => $data['reason'] ?? null, 'printed_by' => Auth::id(), 'printed_at' => now(),
                ]);
            }
        });

        return view('packaging_lists.label', [
            'labelBatches' => $labelBatches, 'format' => $data['format'], 'copies' => $copies,
            'labelTitle' => $packagingList->packing_number.' · All carton labels',
            'autoPrint' => true, 'backUrl' => route('packaging-lists.show', $packagingList),
        ]);
    }

    public function label(PackagingListCarton $carton, Request $request): View
    {
        $data = $request->validate(['format'=>['required',Rule::in(['a4','a5','thermal','thermal150'])],'copies'=>['required','integer','min:1','max:100000'],'reason'=>['nullable','string','max:255']]);
        $carton->load('packagingList.client', 'packagingList.cartons', 'items.product');
        $format = $data['format'];
        $copies = (int) $data['copies'];
        if ($copies > (int) $carton->carton_count) {
            throw ValidationException::withMessages(['copies' => 'Label count cannot exceed the number of cartons in this group.']);
        }
        $startCartonNumber = 1;
        foreach ($carton->packagingList->cartons as $group) {
            if ($group->is($carton)) break;
            $startCartonNumber += (int) $group->carton_count;
        }
        $carton->labelPrints()->create([
            'label_format'=>$format, 'copies'=>$copies, 'reason'=>$data['reason'] ?? null,
            'printed_by'=>Auth::id(), 'printed_at'=>now(),
        ]);
        return view('packaging_lists.label', compact('carton','format','copies','startCartonNumber'));
    }

    private function packingWarnings(array $cartons): array
    {
        $sources = [];
        foreach ($cartons as $cartonIndex => $carton) foreach (($carton['items'] ?? []) as $itemIndex => $item) {
            if (($item['source_quantity'] ?? null) === null) continue;
            $key = ($item['source_type'] ?? 'source').':'.($item['source_item_id'] ?? $cartonIndex.'-'.$itemIndex);
            $sources[$key] ??= [
                'description'=>$item['item_description'] ?? 'Item', 'source'=>(float)$item['source_quantity'],
                'previous'=>(float)($item['previously_packed_quantity'] ?? 0), 'current'=>0,
            ];
            $sources[$key]['current'] += (float) ($item['quantity_per_carton'] ?? 0) * (int) ($carton['carton_count'] ?? 1);
        }
        $warnings = [];
        foreach ($sources as $row) {
            $excess = $row['previous'] + $row['current'] - $row['source'];
            if ($excess > 0) $warnings[] = ['type'=>'overpacked','description'=>$row['description'],'excess'=>round($excess,3)];
        }
        return $warnings;
    }

    private function assertReadyForStatus(PackagingList $list, string $status, PackagingListVerifier $verifier, array $submittedCartons = []): void
    {
        if (! in_array($status, ['confirmed', 'dispatched'], true)) return;

        $inspection = $verifier->inspect($list);
        if ($inspection['failed'] > 0) {
            $failed = collect($inspection['checks'])->where('status', 'fail')->pluck('message')->implode(' ');
            $messages = ['status' => 'Automatic packing checks found issues: '.$failed];
            $identity = collect($inspection['checks'])->firstWhere('key', 'project_identity');
            foreach ($list->cartons->values() as $cartonIndex => $carton) {
                foreach ($carton->items->values() as $itemIndex => $item) {
                    if (in_array($item->id, $identity['unmatched_item_ids'] ?? [])) {
                        $cartonKey = array_keys($submittedCartons)[$cartonIndex] ?? $cartonIndex;
                        $itemKey = array_keys($submittedCartons[$cartonKey]['items'] ?? [])[$itemIndex] ?? $itemIndex;
                        $messages["cartons.$cartonKey.items.$itemKey.project_product_id"] = 'Select the matching active project product for '.$item->item_description.'.';
                    }
                }
            }
            throw ValidationException::withMessages($messages);
        }
    }

    private function nextNumber(): string
    {
        $today = now();
        $startYear = (int) $today->format('n') >= 4 ? (int) $today->format('Y') : (int) $today->format('Y') - 1;
        $financialYear = sprintf('%02d-%02d', $startYear % 100, ($startYear + 1) % 100);
        $prefix = 'MP/PL/'.$financialYear.'/';
        $lastSequence = PackagingList::query()
            ->where('packing_number', 'like', $prefix.'%')
            ->pluck('packing_number')
            ->map(function ($number) use ($prefix) {
                return preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $number, $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    private function formData(PackagingList $list, array $prefillCartons = []): array
    {
        return [
            'list' => $list, 'vendors' => Vendor::orderBy('vendor_name')->get(),
            'clients' => Client::orderBy('company_name')->get(), 'projects' => Project::latest('id')->get(),
            'shipments' => Shipment::latest('id')->limit(500)->get(),
            'salesInvoices' => SalesInvoice::latest('invoice_date')->limit(500)->get(),
            'purchaseInvoices' => PurchaseInvoice::where('invoice_type', PurchaseInvoice::TYPE_BILL)->latest('invoice_date')->limit(500)->get(),
            'prefillCartons' => $prefillCartons,
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'projectProducts' => ProjectProduct::where('status', '!=', 'cancelled')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'project_id', 'product_id', 'product_name', 'sku', 'unit']),
            'statusOptions' => PackagingList::statusOptions(), 'modeOptions' => PackagingList::transportModeOptions(),
        ];
    }

    private function assertPackingEditable(PackagingList $list): void
    {
        $usedForDispatch = $list->cartons()->whereHas('items.shipmentAllocations.shipmentItem.shipment', fn ($query) => $query->whereIn('status', Shipment::dispatchedStatuses()))->exists();
        if ($usedForDispatch) {
            throw ValidationException::withMessages(['status' => 'This confirmed packing is frozen because dispatched shipment quantities consume it. Reverse or cancel the shipment before editing packing content.']);
        }
    }

    private function refreshProjectReadiness(PackagingList $list): void
    {
        if ($workflow = $list->project?->workflow) {
            app(\App\Services\ProjectOperationalReadiness::class)->evaluateAll($workflow, Auth::id());
            $authorization = app(\App\Services\ShipmentDispatchAuthorization::class);
            Shipment::query()->where('project_id', $list->project_id)->where('status', Shipment::STATUS_PLANNING)
                ->with('items.packingAllocations')->get()->each(function ($shipment) use ($authorization, $workflow) {
                    $authorization->evaluate($shipment, $workflow, $authorization->rowsFromShipment($shipment), Auth::id());
                });
        }
    }

    private function shipmentAddress(Shipment $shipment, string $side): string
    {
        return collect([$shipment->{$side.'_address'}, $shipment->{$side.'_city'}, $shipment->{$side.'_state'}, $shipment->{$side.'_pincode'}, $shipment->{$side.'_country'}])->filter()->implode(', ');
    }
}
