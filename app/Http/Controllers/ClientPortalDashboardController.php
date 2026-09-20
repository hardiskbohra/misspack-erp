<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDocument;
use App\Models\ClientPortalInvoice;
use App\Models\ClientPortalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalDashboardController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $client = $this->client($request);
        $portalUser = $this->portalUser($request);

        $projectTotal = 0;
        $projects = collect();
        if ($this->projectsAvailable()) {
            $projectQuery = \App\Models\Project::query()
                ->where('client_id', $client->id)
                ->where('show_client_portal', true);
            $projectTotal = (clone $projectQuery)->count();
            $projects = $projectQuery->latest('id')->limit(3)->get();
        }

        $shipmentTotal = 0;
        $shipments = collect();
        if ($this->shipmentsAvailable() && Schema::hasColumn('shipments', 'client_id') && Schema::hasColumn('shipments', 'show_client_portal')) {
            $shipmentQuery = \App\Models\Shipment::query()
                ->where('client_id', $client->id)
                ->where('show_client_portal', true);
            $shipmentTotal = (clone $shipmentQuery)->count();
            $shipments = $shipmentQuery->latest('id')->limit(3)->get();
        }

        $quoteTotal = 0;
        $quotes = collect();
        if ($this->quotesAvailable()) {
            $quoteQuery = \App\Models\CustomerQuote::query()
                ->where('client_id', $client->id)
                ->when(Schema::hasColumn('customer_quotes', 'show_client_portal'), function ($query) {
                    $query->where('show_client_portal', true);
                })
                ->where('status', '!=', 'draft');
            $quoteTotal = (clone $quoteQuery)->count();
            $quotes = $quoteQuery->latest('id')->limit(3)->get();
        }

        $invoices = ClientPortalInvoice::where('client_id', $client->id)
            ->where('is_public_to_client', true)
            ->latest('id')
            ->limit(5)
            ->get();

        $paymentsTotal = 0;
        if ($this->projectsAvailable() && class_exists(\App\Models\ProjectPayment::class) && Schema::hasTable('project_payments')) {
            $projectIds = \App\Models\Project::where('client_id', $client->id)->where('show_client_portal', true)->pluck('id');
            $paymentsTotal = (float) \App\Models\ProjectPayment::whereIn('project_id', $projectIds)
                ->where('is_public', true)
                ->where('transaction_type', 'inward')
                ->sum('amount');
        }

        $stats = [
            'projects' => $projectTotal,
            'shipments' => $shipmentTotal,
            'quotes' => $quoteTotal,
            'invoices' => ClientPortalInvoice::where('client_id', $client->id)->where('is_public_to_client', true)->count(),
            'documents' => ClientPortalDocument::where('client_id', $client->id)->count(),
            'unread_notifications' => ClientPortalNotification::where('client_id', $client->id)
                ->where(function ($query) use ($portalUser) {
                    $query->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $portalUser->id);
                })
                ->where('is_read', false)
                ->count(),
            'payments_total' => $paymentsTotal,
        ];

        $notifications = ClientPortalNotification::where('client_id', $client->id)
            ->where(function ($query) use ($portalUser) {
                $query->whereNull('client_portal_user_id')->orWhere('client_portal_user_id', $portalUser->id);
            })
            ->latest('id')
            ->limit(8)
            ->get();

        return view('client_portal.dashboard.index', compact('client', 'portalUser', 'stats', 'projects', 'shipments', 'quotes', 'invoices', 'notifications'));
    }
}
