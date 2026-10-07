<?php

namespace App\Http\Controllers;

use App\Services\ProjectReceipts;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * The client's receipts: money received against the projects they can see.
 *
 * The rows are ledger entries tagged to those projects, read through
 * `ProjectReceipts` so this page, the project's own receipts panel, the
 * dashboard's tally and the statement of account all answer the same way. The
 * separate `project_payments` table this page used to read — a copy of the
 * ledger with its own publish flag — is gone: the ledger is the source of
 * truth for project-level money.
 */
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

        $projectIds = ProjectReceipts::publishedProjectIds($this->client($request)->id);

        if (ProjectReceipts::available() && $projectIds !== []) {
            $query = ProjectReceipts::query($projectIds)
                ->when($filters['search'] ?? null, function ($receipts, $search) {
                    $receipts->where(function ($nested) use ($search) {
                        $nested->where('particular', 'like', '%'.$search.'%')
                            ->orWhere('bank_reference_number', 'like', '%'.$search.'%')
                            ->orWhere('invoice_bill_number', 'like', '%'.$search.'%')
                            ->orWhereHas('project', fn ($project) => $project->where('name', 'like', '%'.$search.'%'));
                    });
                })
                ->when($filters['currency'] ?? null, fn ($receipts, $currency) => $receipts->where('currency', strtoupper($currency)))
                ->when($filters['date_from'] ?? null, fn ($receipts, $date) => $receipts->whereDate('entry_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($receipts, $date) => $receipts->whereDate('entry_date', '<=', $date));

            $receipts = (clone $query)
                ->reorder()
                ->latest('entry_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString();

            /* The base query carries a display order, so the grouped read drops
               it before grouping — a column the group does not select cannot be
               ordered by under strict SQL modes. */
            $currencyTotals = (clone $query)
                ->reorder()
                ->select('currency')
                ->selectRaw('COUNT(*) as receipt_count, SUM(credit_amount) as total_amount')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get();
        } else {
            $receipts = new LengthAwarePaginator([], 0, 15, LengthAwarePaginator::resolveCurrentPage());
            $currencyTotals = collect();
        }

        return view('client_portal.payments.index', [
            'receipts' => $receipts,
            'currencyTotals' => $currencyTotals,
            'filters' => $filters,
        ]);
    }
}
