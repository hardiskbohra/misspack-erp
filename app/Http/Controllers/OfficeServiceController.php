<?php

namespace App\Http\Controllers;

use App\Models\CashflowEntry;
use App\Models\OfficeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeServiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $search = $search !== '' ? mb_substr($search, 0, 150) : null;

        $class = $request->query('class', 'all');
        if (! is_string($class) || ! array_key_exists($class, ['all' => 'All'] + OfficeService::classOptions())) {
            $class = 'all';
        }

        $status = $request->query('status', 'all');
        if (! is_string($status) || ! array_key_exists($status, ['all' => 'All'] + OfficeService::statusOptions())) {
            $status = 'all';
        }

        $services = OfficeService::query()
            ->search($search)
            ->when($class !== 'all', fn ($q) => $q->where('service_class', $class))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        $stats = [
            'total' => OfficeService::query()->count(),
            'active' => OfficeService::query()->where('status', OfficeService::STATUS_ACTIVE)->count(),
            'classes' => OfficeService::query()->distinct()->count('service_class'),
            'retainers' => (float) OfficeService::query()
                ->where('status', OfficeService::STATUS_ACTIVE)
                ->where('retainer_cycle', 'monthly')
                ->sum('retainer_amount'),
        ];

        return view('office_services.index', [
            'services' => $services,
            'stats' => $stats,
            'search' => $search,
            'class' => $class,
            'status' => $status,
            'classOptions' => OfficeService::classOptions(),
            'statusOptions' => OfficeService::statusOptions(),
        ]);
    }

    public function create(): View
    {
        return view('office_services.form', [
            'service' => new OfficeService([
                'status' => OfficeService::STATUS_ACTIVE,
                'service_class' => OfficeService::CLASS_HOUSEKEEPING,
                'retainer_cycle' => 'monthly',
                'currency' => 'INR',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['service_number'] = $this->makeNumber();
        $service = OfficeService::create($data);

        return redirect()->route('office-services.show', $service)
            ->with('success', 'Office service saved.');
    }

    public function show(OfficeService $office_service): View
    {
        $payments = collect();
        if (Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'office_service_id')) {
            $payments = CashflowEntry::query()
                ->where('office_service_id', $office_service->id)
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return view('office_services.show', [
            'service' => $office_service,
            'payments' => $payments,
        ]);
    }

    public function edit(OfficeService $office_service): View
    {
        return view('office_services.form', ['service' => $office_service]);
    }

    public function update(Request $request, OfficeService $office_service): RedirectResponse
    {
        $office_service->update($this->validated($request, $office_service));

        return redirect()->route('office-services.show', $office_service)
            ->with('success', 'Office service updated.');
    }

    public function destroy(OfficeService $office_service): RedirectResponse
    {
        if (Schema::hasColumn('cashflow_entries', 'office_service_id')
            && CashflowEntry::query()->where('office_service_id', $office_service->id)->exists()) {
            return back()->with('error', 'This service has cashflow entries. Mark it inactive instead of deleting it.');
        }

        $office_service->delete();

        return redirect()->route('office-services.index')->with('success', 'Office service removed.');
    }

    private function validated(Request $request, ?OfficeService $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_class' => ['required', Rule::in(array_keys(OfficeService::classOptions()))],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(array_keys(OfficeService::statusOptions()))],
            'retainer_amount' => ['nullable', 'numeric', 'min:0'],
            'retainer_cycle' => ['required', Rule::in(array_keys(OfficeService::cycleOptions()))],
            'currency' => ['required', 'in:INR'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:64'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function makeNumber(): string
    {
        $prefix = 'MP-OFS-';
        $last = OfficeService::query()
            ->where('service_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(service_number) DESC')
            ->orderByDesc('service_number')
            ->value('service_number');

        $next = $last ? ((int) preg_replace('/\D/', '', substr($last, strlen($prefix)))) + 1 : 1;
        $next = max($next, 1);

        do {
            $number = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (OfficeService::query()->where('service_number', $number)->exists());

        return $number;
    }
}
