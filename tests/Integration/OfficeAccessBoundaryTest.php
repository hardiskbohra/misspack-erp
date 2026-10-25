<?php

namespace Tests\Integration;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class OfficeAccessBoundaryTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
    }

    public static function officePages(): array
    {
        return array_map(fn ($name) => [$name], ['dashboard', 'dashboards.index', 'clients.index', 'vendors.index',
            'products.index', 'leads.index', 'projects.index', 'shipments.index', 'packaging-lists.index',
            'sales-invoices.index', 'purchase-invoices.index', 'cashflows.index', 'office-services.index',
            'tasks.index', 'assets.index', 'users.index', 'settings.index', 'audit.index']);
    }

    #[DataProvider('officePages')]
    public function test_guest_cannot_read_office_modules(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    #[DataProvider('officePages')]
    public function test_employee_is_redirected_before_an_office_controller_can_run(string $route): void
    {
        $user = new User(['role' => 'employee', 'name' => 'Employee', 'email' => 'employee@example.test']);
        $user->id = 9001;
        $this->actingAs($user)->get(route($route))->assertRedirect(route('my.dashboard'));
    }

    public function test_sales_reader_cannot_read_finance_or_write_sales(): void
    {
        $user = new User(['role' => 'admin', 'name' => 'Reader', 'email' => 'reader@example.test',
            'access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $user->id = 9002;
        $this->actingAs($user)->get(route('cashflows.index'))->assertForbidden();
        $this->post(route('clients.store'), ['name' => 'Unauthorised client'])->assertForbidden();
    }
}
