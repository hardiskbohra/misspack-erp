<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GlobalSearch
{
    public function __construct(private SettingsDirectory $settings)
    {
    }

    public function lookup(string $query, int $perGroup = 4): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return ['q' => $query, 'groups' => [], 'total' => 0];
        }

        $groups = [];

        foreach ($this->sources() as $source) {
            if (! class_exists($source['model']) || ! Schema::hasTable($source['table'])) {
                continue;
            }

            if (! empty($source['route']) && ! Route::has($source['route'])) {
                continue;
            }

            try {
                $rows = ($source['model'])::query()
                    ->search($query)
                    ->limit($perGroup)
                    ->get()
                    ->map(fn ($row) => $source['map']($row))
                    ->filter()
                    ->values()
                    ->all();
            } catch (Throwable $e) {
                continue;
            }

            if ($rows === []) {
                continue;
            }

            $groups[] = [
                'key' => $source['key'],
                'label' => $source['label'],
                'icon' => $source['icon'],
                'results' => $rows,
            ];
        }

        /* Settings are not rows, so they have no model to search and no table to
           check: an area is found by what it holds and by the words a person
           uses for it (nobody types "briefings" when they want the emails off).
           Appended after the records because they are a different kind of
           answer, and the group only appears when it has something to say. */
        $settings = $this->settings->searchHits($query, $perGroup);

        if ($settings !== []) {
            $groups[] = [
                'key' => 'settings',
                'label' => 'Settings',
                'icon' => 'fas fa-sliders',
                'results' => $settings,
            ];
        }

        return [
            'q' => $query,
            'groups' => $groups,
            'total' => collect($groups)->sum(fn ($group) => count($group['results'])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            [
                'key' => 'clients',
                'label' => 'Clients',
                'icon' => 'fa-solid fa-building',
                'model' => Client::class,
                'table' => 'clients',
                'route' => 'clients.show',
                'map' => fn (Client $row) => $this->hit(
                    route('clients.show', $row),
                    $row->company_name ?: $row->client_number,
                    trim(($row->client_number ? $row->client_number.' · ' : '').($row->statusLabel())),
                ),
            ],
            [
                'key' => 'vendors',
                'label' => 'Vendors',
                'icon' => 'fa-solid fa-industry',
                'model' => Vendor::class,
                'table' => 'vendors',
                'route' => 'vendors.show',
                'map' => fn (Vendor $row) => $this->hit(
                    route('vendors.show', $row),
                    $row->vendor_name ?: $row->vendor_number,
                    trim(($row->vendor_number ? $row->vendor_number.' · ' : '').($row->statusLabel())),
                ),
            ],
            [
                'key' => 'products',
                'label' => 'Products',
                'icon' => 'fa-solid fa-box',
                'model' => Product::class,
                'table' => 'products',
                'route' => 'products.show',
                'map' => fn (Product $row) => $this->hit(
                    route('products.show', $row),
                    $row->name ?: $row->product_number,
                    trim(($row->sku ?: $row->product_number).' · '.$row->statusLabel()),
                ),
            ],
            [
                'key' => 'projects',
                'label' => 'Projects',
                'icon' => 'fa-solid fa-diagram-project',
                'model' => Project::class,
                'table' => 'projects',
                'route' => 'projects.show',
                'map' => fn (Project $row) => $this->hit(
                    route('projects.show', $row),
                    $row->name ?: $row->project_number,
                    trim(($row->project_number ? $row->project_number.' · ' : '').($row->statusLabel())),
                ),
            ],
            [
                'key' => 'leads',
                'label' => 'Leads',
                'icon' => 'fa-solid fa-bullseye',
                'model' => Lead::class,
                'table' => 'leads',
                'route' => 'leads.show',
                'map' => fn (Lead $row) => $this->hit(
                    route('leads.show', $row),
                    $row->title ?: $row->lead_number,
                    trim(($row->client_company_name ?: $row->lead_number).' · '.$row->statusLabel()),
                ),
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'icon' => 'fa-solid fa-file-invoice',
                'model' => SalesInvoice::class,
                'table' => 'sales_invoices',
                'route' => 'sales-invoices.show',
                'map' => fn (SalesInvoice $row) => $this->hit(
                    route('sales-invoices.show', $row),
                    $row->invoice_number,
                    trim(($row->client_company_name ?: '').' · '.$row->statusLabel()),
                ),
            ],
            [
                'key' => 'purchases',
                'label' => 'Purchases',
                'icon' => 'fa-solid fa-cart-shopping',
                'model' => PurchaseInvoice::class,
                'table' => 'purchase_invoices',
                'route' => 'purchase-invoices.show',
                'map' => fn (PurchaseInvoice $row) => $this->hit(
                    route('purchase-invoices.show', $row),
                    $row->invoice_number,
                    trim(($row->vendor_company_name ?: $row->typeLabel()).' · '.$row->statusLabel()),
                ),
            ],
            [
                'key' => 'cashflows',
                'label' => 'Cashflow',
                'icon' => 'fa-solid fa-indian-rupee-sign',
                'model' => CashflowEntry::class,
                'table' => 'cashflow_entries',
                'route' => 'cashflows.show',
                'map' => fn (CashflowEntry $row) => $this->hit(
                    route('cashflows.show', $row),
                    $row->particular ?: 'Cashflow entry',
                    trim(($row->related_party_name ?: $row->statusLabel()).($row->entry_date ? ' · '.$row->entry_date->format('d M Y') : '')),
                ),
            ],
            [
                'key' => 'shipments',
                'label' => 'Shipments',
                'icon' => 'fa-solid fa-truck',
                'model' => Shipment::class,
                'table' => 'shipments',
                'route' => 'shipments.show',
                'map' => fn (Shipment $row) => $this->hit(
                    route('shipments.show', $row),
                    $row->shipment_number ?: $row->identity_name,
                    trim(($row->to_name ?: $row->tracking_number ?: '').' · '.$row->statusLabel()),
                ),
            ],
            [
                'key' => 'tasks',
                'label' => 'Tasks',
                'icon' => 'fa-solid fa-list-check',
                'model' => Task::class,
                'table' => 'tasks',
                'route' => 'tasks.show',
                'map' => fn (Task $row) => $this->hit(
                    route('tasks.show', $row),
                    $row->title,
                    $row->statusLabel(),
                ),
            ],
            [
                'key' => 'people',
                'label' => 'People',
                'icon' => 'fa-solid fa-user',
                'model' => User::class,
                'table' => 'users',
                'route' => 'users.show',
                'map' => fn (User $row) => $this->hit(
                    route('users.show', $row),
                    $row->name,
                    trim(($row->email ?: '').($row->role ? ' · '.($row->role === 'admin' ? 'Office' : 'Employee') : '')),
                ),
            ],
        ];
    }

    private function hit(string $url, ?string $title, ?string $subtitle): ?array
    {
        $title = trim((string) $title);

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'subtitle' => trim((string) $subtitle) ?: null,
            'url' => $url,
        ];
    }
}
