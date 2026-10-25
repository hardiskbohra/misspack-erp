<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PermissionMatrix;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PermissionMatrixTest extends TestCase
{
    #[DataProvider('routeRequirements')]
    public function test_route_requires_the_correct_permission(string $route, bool $write, ?string $expected): void
    {
        $this->assertSame($expected, (new PermissionMatrix())->requiredForRoute($route, $write));
    }

    public static function routeRequirements(): array
    {
        return [
            ['dashboard', false, null], ['clients.index', false, 'sales.view'],
            ['clients.store', true, 'sales.manage'], ['sales-invoices.print', false, 'sales.view'],
            ['vendors.index', false, 'purchase.view'], ['purchase-invoices.store', true, 'purchase.manage'],
            ['projects.show', false, 'projects.view'], ['projects.update', true, 'projects.manage'],
            ['projects.work-queue.index', false, 'work.view'], ['projects.exceptions.index', false, 'work.view'],
            ['shipments.index', false, 'operations.view'], ['packaging-lists.store', true, 'operations.manage'],
            ['cashflows.index', false, 'finance.view'], ['assets.dispose', true, 'finance.manage'],
            ['users.show', false, 'people.view'], ['users.photo.update', true, 'people.manage'], ['users.store', true, 'people.manage'],
            ['users.permissions.update', true, 'permissions.manage'], ['settings.index', false, 'settings.manage'],
            ['audit.index', false, 'audit.view'], ['clients.portal.show', false, 'portals.manage'],
            ['vendors.shares.store', true, 'portals.manage'], ['projects.approvals.override', true, 'projects.override'],
            ['projects.financial-closure.store', true, 'projects.override'], ['projects.exceptions.escalate', true, 'projects.override'],
            ['', false, null], ['notes.index', false, null],
        ];
    }

    #[DataProvider('dashboardModules')]
    public function test_module_dashboard_requires_its_own_domain_not_generic_dashboard_access(string $module): void
    {
        $route = (new Route('GET', 'dashboards/{module}', fn () => null))->name('dashboards.show');
        $request = Request::create('/dashboards/'.$module);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->assertSame($module.'.view', (new PermissionMatrix())->requiredFor($request));
    }

    public static function dashboardModules(): array
    {
        return array_map(fn ($module) => [$module], ['sales', 'purchase', 'projects', 'operations', 'finance', 'work']);
    }

    public function test_manage_implies_view_but_view_does_not_imply_manage_or_sensitive_access(): void
    {
        $matrix = new PermissionMatrix();
        foreach (array_keys(PermissionMatrix::GROUPS) as $group) {
            $reader = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => [$group.'.view']]);
            $manager = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => [$group.'.manage']]);
            $this->assertTrue($matrix->allows($reader, $group.'.view'));
            $this->assertFalse($matrix->allows($reader, $group.'.manage'));
            $this->assertTrue($matrix->allows($manager, $group.'.view'));
            $this->assertTrue($matrix->allows($manager, $group.'.manage'));
            foreach (array_keys(PermissionMatrix::SENSITIVE) as $sensitive) $this->assertFalse($matrix->allows($manager, $sensitive));
        }
    }

    public function test_employee_never_gains_office_access_even_with_full_profile_or_explicit_grants(): void
    {
        $matrix = new PermissionMatrix();
        foreach (['full', 'custom'] as $profile) {
            $user = new User(['role' => 'employee', 'access_profile' => $profile, 'permissions' => array_keys($matrix->options())]);
            foreach (array_keys($matrix->options()) as $permission) $this->assertFalse($matrix->allows($user, $permission));
        }
    }

    public function test_empty_custom_profile_is_deny_by_default(): void
    {
        $matrix = new PermissionMatrix();
        $user = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => []]);
        foreach (array_keys($matrix->options()) as $permission) $this->assertFalse($matrix->allows($user, $permission));
    }
}
