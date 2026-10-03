<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPayment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalPaymentController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        if (class_exists(ProjectPayment::class)
            && Schema::hasTable('project_payments')
            && Schema::hasTable('projects')
            && Schema::hasColumn('projects', 'show_client_portal')) {
            $projectIds = Project::query()
                ->where('client_id', $this->client($request)->id)
                ->where('show_client_portal', true)
                ->pluck('id');

            $query = ProjectPayment::query()
                ->with('project')
                ->whereIn('project_id', $projectIds)
                ->visibleToClient()
                ->when($filters['search'] ?? null, function ($payments, $search) {
                    $payments->where(function ($nested) use ($search) {
                        $nested->where('reference_number', 'like', '%'.$search.'%')
                            ->orWhereHas('project', fn ($project) => $project->where('name', 'like', '%'.$search.'%'));
                    });
                })
                ->when($filters['currency'] ?? null, fn ($payments, $currency) => $payments->where('currency', strtoupper($currency)))
                ->when($filters['date_from'] ?? null, fn ($payments, $date) => $payments->whereDate('payment_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($payments, $date) => $payments->whereDate('payment_date', '<=', $date));

            $payments = (clone $query)
                ->latest('payment_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString();

            $currencyTotals = (clone $query)
                ->select('currency')
                ->selectRaw('COUNT(*) as payment_count, SUM(amount) as total_amount')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get();
        } else {
            $payments = new LengthAwarePaginator([], 0, 15, LengthAwarePaginator::resolveCurrentPage());
            $currencyTotals = collect();
        }

        return view('client_portal.payments.index', [
            'payments' => $payments,
            'currencyTotals' => $currencyTotals,
            'filters' => $filters,
        ]);
    }
}
