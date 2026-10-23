<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PackagingList;
use App\Models\PackagingListItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\User;
use App\Services\PackagingListProjectComparison;
use App\Services\PackagingListVerifier;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackagingProjectComparisonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_020000_create_clients_table',
            '2026_06_09_030000_create_vendors_table',
            '2026_06_09_010000_create_shipments_table',
            '2026_06_09_010100_create_shipment_items_table',
            '2026_07_12_010000_create_products_table',
            '2026_07_18_010000_create_projects_table',
            '2026_07_18_010100_create_project_products_table',
            '2026_10_19_120000_add_soft_deletes_to_projects',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_10_04_130000_create_purchase_invoices_table',
            '2026_10_17_100000_create_packaging_lists_table',
            '2026_10_17_120000_expand_packaging_lists_for_source_packing_and_labels',
            '2026_10_18_160000_create_packaging_list_verifications_table',
            '2026_10_18_170000_create_project_execution_workflow_tables',
            '2026_10_18_173000_create_project_quality_inspections',
            '2026_10_18_174000_create_shipment_packing_allocations',
            '2026_10_21_100000_add_packing_group_to_packaging_list_cartons',
            '2026_10_21_110000_add_print_columns_to_packaging_lists',
            '2026_10_21_120000_add_print_settings_to_packaging_lists',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $this->actingAs(User::create(['name' => 'Operations', 'email' => 'operations@example.test', 'password' => 'password', 'role' => 'admin']));
    }

    private function project(string $number = 'PRJ-01'): Project
    {
        $client = Client::firstOrCreate(['client_number' => 'CL-01'], ['company_name' => 'Buyer']);
        $project = new Project(['client_id' => $client->id, 'project_number' => $number, 'name' => 'Bottle order']);
        $project->forceFill(['public_token' => 'comparison-'.$number])->save();
        return $project;
    }

    private function list(?Project $project): PackagingList
    {
        return PackagingList::create(['packing_number' => 'PL-01', 'packing_date' => now()->toDateString(),
            'project_id' => $project?->id, 'from_name' => 'MissPack', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer']);
    }

    private function projectProduct(Project $project, string $name, array $attributes = []): ProjectProduct
    {
        return ProjectProduct::create(array_merge(['project_id' => $project->id, 'product_name' => $name,
            'sku' => 'SKU-'.$name, 'quantity' => 100, 'unit' => 'pcs'], $attributes));
    }

    private function line(PackagingList $list, string $description, ?ProjectProduct $product = null, array $attributes = []): PackagingListItem
    {
        $carton = $list->cartons()->first() ?: $list->cartons()->create(['carton_label' => 'Bottles', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        return $carton->items()->create(array_merge(['item_description' => $description,
            'project_product_id' => $product?->id, 'quantity_per_carton' => 10, 'total_quantity' => 10, 'unit' => 'pcs'], $attributes));
    }

    private function compare(PackagingList $list): array
    {
        return app(PackagingListProjectComparison::class)->inspect($list->fresh());
    }

    public function test_comparison_shows_the_actual_packing_catalog_and_linked_project_product(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $catalog = Product::create(['product_number' => 'P-01', 'name' => 'Catalog amber bottle']);
        $linked = $this->projectProduct($project, 'Amber bottle 100 ml');
        $this->line($list, 'Amber glass 100ml — packed', $linked, ['product_id' => $catalog->id, 'sku' => 'PACK-100']);
        $comparison = $this->compare($list);
        $row = $comparison['rows'][0];
        $this->assertSame('Amber glass 100ml — packed', $row['packing_description']);
        $this->assertSame('Catalog amber bottle', $row['packing_product']);
        $this->assertSame('Amber bottle 100 ml', $row['project_product']);
        $this->assertSame('PACK-100', $row['packing_sku']);
        $this->assertSame('SKU-Amber bottle 100 ml', $row['project_sku']);
        $this->assertSame('warning', $row['status']);
        $this->assertSame(0, $comparison['unresolved']);
        $inspection = app(PackagingListVerifier::class)->inspect($list->fresh());
        $this->assertSame(0, $inspection['failed']);
        $this->get(route('packaging-lists.show', $list))->assertOk()
            ->assertSee('Project product comparison')->assertSee('Packing list product')
            ->assertSee('Linked project product')->assertSee('Review difference')
            ->assertSee('Catalog amber bottle')->assertSee('Amber bottle 100 ml')
            ->assertSee('PACK-100')->assertSee('Actual products in this project');
    }

    public function test_six_unmatched_rows_are_visible_against_actual_project_products_without_guessing(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        for ($i = 1; $i <= 6; $i++) {
            $this->projectProduct($project, 'Ordered bottle '.$i);
            $this->line($list, 'Packing label '.$i);
        }
        $comparison = $this->compare($list);
        $this->assertSame(6, $comparison['unresolved']);
        $this->assertCount(6, $comparison['rows']);
        $this->assertCount(6, $comparison['products']);
        $response = $this->get(route('packaging-lists.show', $list))->assertOk()
            ->assertSee('6 need mapping')->assertSee('No project product linked');
        for ($i = 1; $i <= 6; $i++) $response->assertSee('Packing label '.$i)->assertSee('Ordered bottle '.$i);
        $this->assertSame(6, PackagingListItem::whereNull('project_product_id')->count());
    }

    public function test_cancelled_and_wrong_project_links_show_the_actual_names_but_fail_identity_checks(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $cancelled = $this->projectProduct($project, 'Cancelled bottle', ['status' => 'cancelled']);
        $foreign = $this->projectProduct($this->project('PRJ-02'), 'Other project bottle');
        $this->line($list, 'First packing label', $cancelled);
        $this->line($list, 'Second packing label', $foreign);
        $comparison = $this->compare($list);
        $this->assertSame(['Cancelled product', 'Wrong project'], array_column($comparison['rows'], 'label'));
        $this->assertSame(['Cancelled bottle', 'Other project bottle'], array_column($comparison['rows'], 'project_product'));
        $this->assertSame(2, $comparison['unresolved']);
        $this->assertCount(1, $comparison['products']);
        $this->get(route('packaging-lists.edit', $list))->assertOk()->assertSee('Unavailable link #'.$cancelled->id)->assertSee('Unavailable link #'.$foreign->id);
        $this->assertGreaterThan(0, app(PackagingListVerifier::class)->inspect($list->fresh())['failed']);
    }

    public function test_matched_identical_names_pass_without_review_and_comparison_does_not_change_records(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $linked = $this->projectProduct($project, 'Amber Bottle');
        $item = $this->line($list, ' amber   bottle ', $linked);
        $before = $item->fresh()->getAttributes();
        $comparison = $this->compare($list);
        $this->assertSame('pass', $comparison['rows'][0]['status']);
        $this->assertSame(0, $comparison['review']);
        $this->assertSame($before, $item->fresh()->getAttributes());
    }

    public function test_catalog_sku_and_unit_differences_are_review_warnings_not_confirmation_failures(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $linked = $this->projectProduct($project, 'Bottle');
        $this->line($list, 'Bottle', $linked, ['sku' => 'CUSTOM-SKU']);
        $this->line($list, 'Bottle', $linked, ['unit' => 'sets']);
        $orderedCatalog = Product::create(['product_number' => 'P-01', 'name' => 'Ordered catalog bottle']);
        $packingCatalog = Product::create(['product_number' => 'P-02', 'name' => 'Packing catalog bottle']);
        $linked->update(['product_id' => $orderedCatalog->id]);
        $this->line($list, 'Bottle', $linked, ['product_id' => $packingCatalog->id]);
        $inspection = app(PackagingListVerifier::class)->inspect($list->fresh());
        $this->assertSame(3, $inspection['project_comparison']['review']);
        $this->assertSame('Packing catalog bottle', $inspection['project_comparison']['rows'][2]['packing_product']);
        $this->assertSame(0, $inspection['failed']);
        $this->assertSame('CUSTOM-SKU', $inspection['project_comparison']['rows'][0]['packing_sku']);
        $this->assertSame('sets', $inspection['project_comparison']['rows'][1]['packing_unit']);
    }

    public function test_editor_exposes_project_mapping_and_accepts_an_explicit_valid_choice(): void
    {
        $project = $this->project();
        $linked = $this->projectProduct($project, 'Ordered bottle');
        $this->projectProduct($project, 'Other bottle');
        $list = $this->list($project);
        $this->line($list, 'Customer packing description');
        $this->get(route('packaging-lists.edit', $list))->assertOk()->assertSee('data-project-product', false)
            ->assertSee('Ordered bottle')->assertDontSee('type="hidden" name="cartons[0][items][0][project_product_id]"', false);
        $this->put(route('packaging-lists.update', $list), $this->payload($list, [$linked->id]))->assertRedirect(route('packaging-lists.show', $list));
        $this->assertSame('confirmed', $list->fresh()->status);
        $this->assertSame($linked->id, $list->cartons()->first()->items()->first()->project_product_id);
    }

    public function test_confirm_errors_identify_the_original_dynamic_row_keys_and_drafts_remain_saveable(): void
    {
        $project = $this->project();
        $this->projectProduct($project, 'First ordered product');
        $this->projectProduct($project, 'Second ordered product');
        $list = $this->list($project);
        $this->line($list, 'Packing description');
        $payload = $this->payload($list, [null]);
        $payload['cartons'][8] = $payload['cartons'][0]; unset($payload['cartons'][0]);
        $payload['cartons'][8]['items'][170000] = $payload['cartons'][8]['items'][0]; unset($payload['cartons'][8]['items'][0]);
        $this->from(route('packaging-lists.edit', $list))->put(route('packaging-lists.update', $list), $payload)
            ->assertSessionHasErrors(['status', 'cartons.8.items.170000.project_product_id']);
        $this->assertSame('draft', $list->fresh()->status);
        $payload['status'] = 'draft';
        $this->put(route('packaging-lists.update', $list), $payload)->assertRedirect(route('packaging-lists.show', $list));
        $this->assertSame('draft', $list->fresh()->status);
    }

    public function test_cancelled_or_foreign_project_selections_are_rejected_by_the_save_endpoint(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $this->line($list, 'Packing description');
        $cancelled = $this->projectProduct($project, 'Cancelled bottle', ['status' => 'cancelled']);
        $foreign = $this->projectProduct($this->project('PRJ-02'), 'Foreign bottle');
        foreach ([$cancelled, $foreign] as $product) {
            $this->put(route('packaging-lists.update', $list), $this->payload($list, [$product->id]))
                ->assertSessionHasErrors('cartons.0.items.0.project_product_id');
        }
        $this->assertNull($list->cartons()->first()->items()->first()->project_product_id);
    }

    public function test_names_are_escaped_and_lists_without_a_project_do_not_display_a_false_check(): void
    {
        $project = $this->project();
        $list = $this->list($project);
        $linked = $this->projectProduct($project, '<script>project()</script>');
        $this->line($list, '<script>packing()</script>', $linked);
        $html = view('packaging_lists.partials.project-comparison', ['list' => $list, 'comparison' => $this->compare($list)])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;packing()', $html);
        $this->assertStringContainsString('&lt;script&gt;project()', $html);
        $list->project_id = null;
        $this->assertSame([], app(PackagingListProjectComparison::class)->inspect($list)['rows']);
    }

    private function payload(PackagingList $list, array $ids): array
    {
        return ['packing_number' => $list->packing_number, 'packing_date' => now()->toDateString(),
            'status' => 'confirmed', 'transport_mode' => 'road', 'project_id' => $list->project_id,
            'from_name' => 'MissPack', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer',
            'cartons' => [['packing_mode' => 'grouped', 'carton_type' => 'box', 'carton_count' => 1,
                'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2,
                'gross_weight_per_carton' => 3, 'items' => array_map(fn ($id) => [
                    'project_product_id' => $id, 'item_description' => 'Customer packing description',
                    'quantity_per_carton' => 10, 'total_quantity' => 10, 'unit' => 'pcs',
                ], $ids)]]];
    }
}
