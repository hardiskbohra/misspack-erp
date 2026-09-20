<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalPaymentController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $payments = collect();
        $totals = ['inward' => 0, 'outward' => 0, 'net' => 0];

        // if ($this->projectsAvailable() && class_exists(\App\Models\ProjectPayment::class) && Schema::hasTable('project_payments')) {
        //     $projectIds = \App\Models\Project::where('client_id', $this->client($request)->id)->where('show_client_portal', true)->pluck('id');
        //     $payments = \App\Models\ProjectPayment::with('project')
        //         ->whereIn('project_id', $projectIds)
        //         ->where('is_public', true)
        //         ->latest('payment_date')
        //         ->paginate(15)
        //         ->withQueryString();

            // $all = \App\Models\ProjectPayment::whereIn('project_id', $projectIds)->where('is_public', true)->get();
            // $totals['inward'] = (float) $all->where('transaction_type', 'inward')->sum('amount');
            // $totals['outward'] = (float) $all->where('transaction_type', 'outward')->sum('amount');
            // $totals['net'] = $totals['inward'] - $totals['outward'];
            
        // }
        
        $payments = \App\Models\CashflowEntry::with('project')
            ->where('client_id', $this->client($request)->id)
            ->latest('entry_date')
            ->paginate(15)
            ->withQueryString();    
        
        $all = \App\Models\CashflowEntry::where('client_id', $this->client($request)->id)->get();
        $totals['inward'] = (float) $all->where('transaction_type', 'credit')->sum('credit_amount');
        $totals['invoiced'] = (float) $all->whereNotNull('invoice_bill_number')->sum('credit_amount');
        $totals['outward'] = (float) $all->where('transaction_type', 'debit')->sum('debit_amount');
        $totals['net'] = $totals['inward'] - $totals['outward'];

        return view('client_portal.payments.index', compact('payments', 'totals'));
    }
}
