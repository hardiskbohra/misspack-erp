<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Route;
use Tests\TestCase;

class TopbarQuickMenuTest extends TestCase
{
    private function navigation(): array
    {
        $items = [];
        foreach ([
            ['Shipments', 'shipments'], ['Cashflow', 'cashflows'], ['Invoices', 'sales-invoices'],
            ['Purchases', 'purchase-invoices'], ['Projects', 'projects'],
            ['Packaging lists', 'packaging-lists'], ['Clients', 'clients'], ['Vendors', 'vendors'],
            ['Settings', 'settings'],
        ] as [$label, $prefix]) {
            $items[] = ['label' => $label, 'route' => $prefix.'.index', 'active' => $prefix.'.*',
                'icon' => 'fa-solid fa-box', 'except' => $prefix === 'projects' ? ['projects.work-queue.*'] : []];
        }
        return $items;
    }

    private function office(array $attributes = []): void
    {
        $user = new User(array_merge(['name' => 'Office', 'role' => 'admin', 'access_profile' => 'full'], $attributes));
        $user->id = 1;
        $this->actingAs($user);
    }

    private function html(?array $items = null): string
    {
        return view('layouts.partials.quick-menu', ['sidebarItems' => $items ?? $this->navigation()])->render();
    }

    public function test_full_office_menu_contains_frequent_modules_with_real_destinations(): void
    {
        $this->office();
        $html = $this->html();
        $this->assertSame(9, substr_count($html, 'role="menuitem"'));
        foreach (array_slice($this->navigation(), 0, 8) as $item) {
            $this->assertStringContainsString('href="'.route($item['route']).'"', $html);
            $this->assertStringContainsString($item['label'], $html);
        }
        $this->assertStringNotContainsString('>Settings<', $html);
        $this->assertStringContainsString('aria-controls="topbarQuickMenuPanel"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
    }

    public function test_custom_access_only_shows_permitted_modules_including_manage_implies_view(): void
    {
        foreach ([
            ['finance.view', ['Cashflow']], ['operations.manage', ['Shipments', 'Packaging lists']],
            ['sales.view', ['Invoices', 'Clients']], ['purchase.view', ['Purchases', 'Vendors']],
            ['projects.manage', ['Projects']],
        ] as [$permission, $labels]) {
            $this->office(['access_profile' => 'custom', 'permissions' => [$permission]]);
            $html = $this->html();
            $this->assertSame(count($labels) + 1, substr_count($html, 'role="menuitem"'));
            $this->assertStringContainsString('href="'.route('notes.index').'"', $html);
            foreach ($this->navigation() as $item) {
                $this->assertSame(in_array($item['label'], $labels), str_contains($html, 'href="'.route($item['route']).'"'));
            }
        }
    }

    public function test_employee_receives_private_notes_only_even_with_full_profile(): void
    {
        $this->office(['role' => 'employee']);
        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'role="menuitem"'));
        $this->assertStringContainsString('href="'.route('notes.index').'"', $html);
    }

    public function test_no_permitted_modules_still_exposes_only_private_notes(): void
    {
        $this->office(['access_profile' => 'custom', 'permissions' => ['settings.manage']]);
        $html = $this->html();
        $this->assertSame(1, substr_count($html, 'role="menuitem"'));
        $this->assertStringContainsString('href="'.route('notes.index').'"', $html);
    }

    public function test_guest_does_not_receive_office_shortcuts(): void
    {
        $this->assertStringNotContainsString('data-quick-menu', $this->html());
    }

    public function test_current_module_is_highlighted_but_excluded_subroutes_are_not(): void
    {
        $this->office();
        foreach (['projects.show' => true, 'projects.work-queue.index' => false] as $name => $expected) {
            $route = new Route('GET', '/test', []);
            $route->name($name);
            request()->setRouteResolver(fn () => $route);
            $html = $this->html();
            $this->assertSame($expected ? 1 : 0, substr_count($html, 'aria-current="page"'));
        }
    }

    public function test_quick_menu_reuses_sidebar_catalog_and_does_not_invent_missing_modules(): void
    {
        $this->office();
        $items = [$this->navigation()[0]];
        $items[0]['label'] = 'Shipping desk';
        $html = $this->html($items);
        $this->assertStringContainsString('Shipping desk', $html);
        $this->assertSame(2, substr_count($html, 'role="menuitem"'));
        $this->assertStringNotContainsString('href="'.route('cashflows.index').'"', $html);
    }

    public function test_launcher_is_icon_only_but_has_an_accessible_name(): void
    {
        $this->office();
        $html = $this->html();
        preg_match('/<button[^>]*aria-label="Quick menu"[^>]*>(.*?)<\/button>/s', $html, $button);
        $this->assertNotEmpty($button);
        $this->assertSame('', trim(strip_tags($button[1])));
        $this->assertStringContainsString('data-ui-tooltip="Quick menu"', $html);
    }

    public function test_private_notes_moves_into_quick_menu_for_both_roles_without_office_privilege(): void
    {
        foreach ([['role'=>'admin'], ['role'=>'admin','access_profile'=>'custom','permissions'=>['settings.manage']], ['role'=>'employee']] as $attributes) {
            $this->office($attributes);
            $html = $this->html();
            $this->assertStringContainsString('href="'.route('notes.index').'"', $html);
            if (($attributes['role'] ?? '') === 'employee') {
                $this->assertSame(1, substr_count($html, 'role="menuitem"'));
                $this->assertStringNotContainsString('href="'.route('cashflows.index').'"', $html);
            }
        }
    }

}
