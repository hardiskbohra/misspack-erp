<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\Product;
use App\Models\Project;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
                    ->orWhere('ship_to_name', 'like', "%{$search}%");
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

    public function create(Request $request): View
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

        if ($shipment = Shipment::find((int) $request->query('shipment'))) {
            $list->shipment_id = $shipment->id;
            $list->project_id = $shipment->project_id;
            $list->client_id = $shipment->client_id;
            $list->vendor_id = $shipment->vendor_id;
            $list->sales_invoice_id = $shipment->sales_invoice_id;
            $list->transport_mode = in_array($shipment->shipment_mode, array_keys(PackagingList::transportModeOptions()), true) ? $shipment->shipment_mode : 'air';
            $list->ship_to_name = $shipment->to_name;
            $list->ship_to_address = $this->shipmentAddress($shipment, 'to');
            $list->from_name = $shipment->from_name;
            $list->from_address = $this->shipmentAddress($shipment, 'from');
        }

        return view('packaging_lists.form', $this->formData($list));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = DB::transaction(function () use ($data) {
            $cartons = $data['cartons'];
            unset($data['cartons']);
            $data['created_by'] = Auth::id();
            $data['packing_number'] = $data['packing_number'] ?: $this->nextNumber();
            $list = PackagingList::create($data);
            $this->syncCartons($list, $cartons);
            return $list;
        });

        return redirect()->route('packaging-lists.show', $list)->with('success', 'Packaging list created successfully.');
    }

    public function show(PackagingList $packagingList): View
    {
        $packagingList->load(['cartons.items.product', 'vendor', 'client', 'project', 'shipment', 'salesInvoice', 'creator']);
        return view('packaging_lists.show', ['list' => $packagingList]);
    }

    public function edit(PackagingList $packagingList): View
    {
        $packagingList->load('cartons.items');
        return view('packaging_lists.form', $this->formData($packagingList));
    }

    public function update(Request $request, PackagingList $packagingList): RedirectResponse
    {
        $data = $this->validated($request, $packagingList);
        DB::transaction(function () use ($data, $packagingList) {
            $cartons = $data['cartons'];
            unset($data['cartons']);
            $packagingList->update($data);
            $packagingList->cartons()->delete();
            $this->syncCartons($packagingList, $cartons);
        });

        return redirect()->route('packaging-lists.show', $packagingList)->with('success', 'Packaging list updated successfully.');
    }

    public function destroy(PackagingList $packagingList): RedirectResponse
    {
        $packagingList->delete();
        return redirect()->route('packaging-lists.index')->with('success', 'Packaging list deleted successfully.');
    }

    public function print(PackagingList $packagingList): View
    {
        $packagingList->load(['cartons.items', 'vendor', 'client', 'project', 'shipment']);
        return view('packaging_lists.print', ['list' => $packagingList, 'organisation' => Organisation::current()]);
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
                $newCarton = $copy->cartons()->create($carton->only(['carton_label', 'carton_count', 'length_cm', 'width_cm', 'height_cm', 'net_weight_per_carton', 'gross_weight_per_carton', 'sort_order']));
                $newCarton->items()->createMany($carton->items->map->only(['product_id', 'item_description', 'quantity_per_carton', 'total_quantity', 'unit', 'sort_order'])->all());
            }
            $copy->recalculate();
            return $copy;
        });

        return redirect()->route('packaging-lists.edit', $copy)->with('success', 'Packaging list duplicated. Review it before confirming.');
    }

    private function validated(Request $request, ?PackagingList $list = null): array
    {
        return $request->validate([
            'packing_number' => ['required', 'string', 'max:60', Rule::unique('packaging_lists')->ignore($list)],
            'packing_date' => ['required', 'date'], 'invoice_number' => ['nullable', 'string', 'max:100'],
            'transport_mode' => ['required', Rule::in(array_keys(PackagingList::transportModeOptions()))],
            'status' => ['required', Rule::in(array_keys(PackagingList::statusOptions()))],
            'vendor_id' => ['nullable', 'exists:vendors,id'], 'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'], 'shipment_id' => ['nullable', 'exists:shipments,id'],
            'sales_invoice_id' => ['nullable', 'exists:sales_invoices,id'],
            'from_name' => ['required', 'string', 'max:255'], 'from_address' => ['nullable', 'string'],
            'from_contact' => ['nullable', 'string', 'max:255'], 'from_email' => ['nullable', 'email', 'max:255'],
            'bill_to_name' => ['required', 'string', 'max:255'], 'bill_to_address' => ['nullable', 'string'],
            'ship_to_name' => ['required', 'string', 'max:255'], 'ship_to_address' => ['nullable', 'string'],
            'declaration' => ['nullable', 'string'], 'customer_signature' => ['nullable', 'string', 'max:255'],
            'supplier_signature' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string'],
            'cartons' => ['required', 'array', 'min:1', 'max:200'],
            'cartons.*.carton_label' => ['nullable', 'string', 'max:100'],
            'cartons.*.carton_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'cartons.*.length_cm' => ['required', 'numeric', 'gt:0'], 'cartons.*.width_cm' => ['required', 'numeric', 'gt:0'],
            'cartons.*.height_cm' => ['required', 'numeric', 'gt:0'],
            'cartons.*.net_weight_per_carton' => ['required', 'numeric', 'min:0'],
            'cartons.*.gross_weight_per_carton' => ['required', 'numeric', 'min:0', 'gte:cartons.*.net_weight_per_carton'],
            'cartons.*.items' => ['required', 'array', 'min:1', 'max:200'],
            'cartons.*.items.*.product_id' => ['nullable', 'exists:products,id'],
            'cartons.*.items.*.item_description' => ['required', 'string', 'max:500'],
            'cartons.*.items.*.quantity_per_carton' => ['required', 'numeric', 'min:0'],
            'cartons.*.items.*.total_quantity' => ['required', 'numeric', 'min:0'],
            'cartons.*.items.*.unit' => ['required', 'string', 'max:30'],
        ]);
    }

    private function syncCartons(PackagingList $list, array $cartons): void
    {
        foreach (array_values($cartons) as $cartonIndex => $row) {
            $items = $row['items']; unset($row['items']); $row['sort_order'] = $cartonIndex;
            $carton = $list->cartons()->create($row);
            foreach (array_values($items) as $itemIndex => $item) {
                // Total quantity is derived, never trusted from a stale or edited browser value.
                $item['total_quantity'] = round((float) $item['quantity_per_carton'] * (int) $carton->carton_count, 3);
                $item['sort_order'] = $itemIndex;
                $carton->items()->create($item);
            }
        }
        $list->recalculate();
    }

    private function nextNumber(): string
    {
        $next = ((int) PackagingList::max('id')) + 1;
        return 'PL'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function formData(PackagingList $list): array
    {
        return [
            'list' => $list, 'vendors' => Vendor::orderBy('vendor_name')->get(),
            'clients' => Client::orderBy('company_name')->get(), 'projects' => Project::latest('id')->get(),
            'shipments' => Shipment::latest('id')->limit(500)->get(),
            'salesInvoices' => SalesInvoice::latest('invoice_date')->limit(500)->get(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'statusOptions' => PackagingList::statusOptions(), 'modeOptions' => PackagingList::transportModeOptions(),
        ];
    }

    private function shipmentAddress(Shipment $shipment, string $side): string
    {
        return collect([$shipment->{$side.'_address'}, $shipment->{$side.'_city'}, $shipment->{$side.'_state'}, $shipment->{$side.'_pincode'}, $shipment->{$side.'_country'}])->filter()->implode(', ');
    }
}
