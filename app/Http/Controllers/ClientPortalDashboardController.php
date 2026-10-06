<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalConversation;
use App\Models\ClientPortalDocument;
use App\Models\ClientPortalNotification;
use App\Models\Project;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalDashboardController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $client = $this->client($request);
        $portalUser = $this->portalUser($request);

        $projects = collect();
        $projectTotal = 0;
        $projectQuery = Project::query()
            ->where('client_id', $client->id)
            ->where('show_client_portal', true);
        $projectTotal = (clone $projectQuery)->count();
        $projects = $projectQuery->latest('id')->limit(4)->get();

        $shipments = collect();
        $shipmentTotal = 0;
        if ($this->shipmentsAvailable()
            && Schema::hasColumn('shipments', 'client_id')
            && Schema::hasColumn('shipments', 'show_client_portal')) {
            $shipmentQuery = \App\Models\Shipment::query()
                ->where('client_id', $client->id)
                ->where('show_client_portal', true);
            $shipmentTotal = (clone $shipmentQuery)->count();
            $shipments = $shipmentQuery->latest('id')->limit(4)->get();
        }

        $salesInvoices = collect();
        $salesInvoiceQuery = SalesInvoice::query()
            ->where('client_id', $client->id)
            ->where('show_client_portal', true)
            ->where('status', '!=', 'draft');
        $salesInvoiceTotal = (clone $salesInvoiceQuery)->count();
        $salesInvoices = $salesInvoiceQuery
            ->withClientPortalReceived()
            ->latest('invoice_date')
            ->latest('id')
            ->limit(4)
            ->get();

        /* Receipts are ledger entries on this client's published projects, read
           through the one service that defines them, so the dashboard's count
           and the payments page can never disagree. */
        $paymentTotal = \App\Services\ProjectReceipts::countForClient($client->id);

        $notificationScope = fn ($query) => $query
            ->where('client_id', $client->id)
            ->where(fn ($userScope) => $userScope
                ->whereNull('client_portal_user_id')
                ->orWhere('client_portal_user_id', $portalUser->id));

        $notifications = ClientPortalNotification::query()
            ->where($notificationScope)
            ->latest('id')
            ->limit(6)
            ->get();

        $supportQuery = ClientPortalConversation::query()->where('client_id', $client->id);
        $supportConversations = (clone $supportQuery)
            ->withCount('messages')
            ->latest('last_message_at')
            ->limit(3)
            ->get();
        $openSupportCount = (clone $supportQuery)->whereIn('status', ['open', 'waiting'])->count();
        $unreadSupportCount = (clone $supportQuery)
            ->whereHas('messages', fn ($messages) => $messages
                ->where('sender_type', 'staff')
                ->whereNull('read_at'))
            ->count();

        $stats = [
            'projects' => $projectTotal,
            'shipments' => $shipmentTotal,
            'invoices' => $salesInvoiceTotal,
            'payments' => $paymentTotal,
            'documents' => ClientPortalDocument::query()
                ->where('client_id', $client->id)
                ->where('is_public_to_client', true)
                ->count(),
            'unread_notifications' => ClientPortalNotification::query()
                ->where($notificationScope)
                ->where('is_read', false)
                ->count(),
            'support_open' => $openSupportCount,
            'support_unread' => $unreadSupportCount,
        ];

        return view('client_portal.dashboard.index', compact(
            'client',
            'portalUser',
            'stats',
            'projects',
            'shipments',
            'salesInvoices',
            'notifications',
            'supportConversations'
        ));
    }
}
