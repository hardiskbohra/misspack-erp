<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionMatrix
{
    public const GROUPS = [
        'dashboard' => ['label' => 'Dashboards', 'description' => 'ERP overview and module reporting'],
        'work' => ['label' => 'Work management', 'description' => 'Tasks, coordinator queue, exceptions and feedback'],
        'sales' => ['label' => 'Sales', 'description' => 'Clients, leads, products and sales invoices'],
        'purchase' => ['label' => 'Purchase', 'description' => 'Vendors, services and purchase documents'],
        'projects' => ['label' => 'Projects', 'description' => 'Projects, workflow and operational execution'],
        'operations' => ['label' => 'Operations', 'description' => 'Shipments, tracking and Packaging Lists'],
        'finance' => ['label' => 'Finance', 'description' => 'Cashflow, statements, recurring entries and assets'],
        'people' => ['label' => 'People & payroll', 'description' => 'Users, salary, payslips and employee documents'],
        'portals' => ['label' => 'Portal administration', 'description' => 'Client/vendor access, secure links and support'],
    ];

    public const SENSITIVE = [
        'projects.override' => ['label' => 'Override project controls', 'description' => 'Manager gate, approval, exception and closure decisions'],
        'settings.manage' => ['label' => 'Manage organisation settings', 'description' => 'Company-wide configuration and master values'],
        'audit.view' => ['label' => 'View audit register', 'description' => 'Security and activity evidence across the ERP'],
        'permissions.manage' => ['label' => 'Manage access profiles', 'description' => 'Grant and revoke office permissions for other users'],
    ];

    public function options(): array
    {
        $options = [];
        foreach (self::GROUPS as $key => $group) {
            $options[$key.'.view'] = ['group' => $group['label'], 'label' => 'View', 'description' => $group['description']];
            $options[$key.'.manage'] = ['group' => $group['label'], 'label' => 'Create and change', 'description' => $group['description']];
        }
        foreach (self::SENSITIVE as $key => $definition) $options[$key] = ['group' => 'Sensitive controls'] + $definition;
        return $options;
    }

    public function requiredFor(Request $request): ?string
    {
        if ($request->routeIs('dashboards.show')) {
            return match ((string) $request->route('module')) {
                'sales' => 'sales.view', 'purchase' => 'purchase.view', 'projects' => 'projects.view',
                'operations' => 'operations.view', 'finance' => 'finance.view', 'work' => 'work.view',
                default => 'dashboard.view',
            };
        }
        return $this->requiredForRoute((string) $request->route()?->getName(), ! $request->isMethodSafe());
    }

    public function requiredForRoute(string $route, bool $write = false): ?string
    {
        if ($route === '') return null;
        // A permission-filtered directory is an entry point, not cross-module data access.
        if ($route === 'dashboards.index') return null;
        if ($route === 'users.permissions.update') return 'permissions.manage';
        if (Str::is(['projects.*override*','projects.approvals.override*','projects.financial-closure.*','projects.exceptions.escalate'], $route)) return 'projects.override';
        if (Str::is(['vendors.portal.*','vendors.shares.*','clients.portal.*'], $route)) return 'portals.manage';
        if (Str::is('audit.*', $route)) return 'audit.view';
        if (Str::is('settings.*', $route)) return 'settings.manage';

        $group = match (true) {
            Str::is(['dashboard','dashboards.*'], $route) => 'dashboard',
            Str::is(['tasks.*','projects.work-queue.*','projects.exceptions.*','feedback.*','briefings.*','office-alerts.*'], $route) => 'work',
            Str::is(['clients.*','leads.*','products.*','sales-invoices.*','price-calculator.*'], $route) => 'sales',
            Str::is(['vendors.*','purchase-invoices.*','office-services.*'], $route) => 'purchase',
            Str::is(['projects.*'], $route) => 'projects',
            Str::is(['shipments.*','packaging-lists.*'], $route) => 'operations',
            Str::is(['cashflows.*','assets.*'], $route) => 'finance',
            Str::is(['users.*'], $route) => 'people',
            default => null,
        };
        if (! $group) return null;
        return $group.($write ? '.manage' : '.view');
    }

    public function allows(User $user, string $permission): bool
    {
        if ($user->isEmployee()) return false;
        if (($user->access_profile ?: 'full') === 'full') return true;
        $permissions = $user->permissions ?: [];
        if (in_array($permission, $permissions, true)) return true;
        if (str_ends_with($permission, '.view')) return in_array(Str::beforeLast($permission, '.').'.manage', $permissions, true);
        return false;
    }
}
