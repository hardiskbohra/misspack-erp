<?php

namespace Tests\Integration;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Exercises the full stock workflow against an isolated disposable schema. */
class StockReconciliationTest extends TestCase
{
    use IsolatedDatabase;

    private User $counter;
    private User $reviewer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        $this->counter = $this->user('counter', ['operations.manage', 'sales.manage']);
        $this->reviewer = $this->user('reviewer', ['operations.manage']);
        $this->product = Product::create(['product_number' => 'STOCK-P-1', 'name' => 'Fictional bottle', 'sku' => 'BOTTLE']);
        $this->actingAs($this->counter);
    }

    private function user(string $name, array $permissions): User
    {
        return User::create(['name' => ucfirst($name), 'email' => $name.'@stock.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
    }

    private function makeItem(string $variant = 'Amber 100ml', ?string $batch = 'B-01', string $opening = '10.125'): StockItem
    {
        $this->post(route('stock.items.store'), ['product_id' => $this->product->id, 'variant' => $variant,
            'batch_code' => $batch, 'expires_on' => $batch ? '2027-01-31' : null, 'unit' => 'pcs',
            'opening_quantity' => $opening, 'reason' => 'Verified opening physical balance'])->assertRedirect()->assertSessionHasNoErrors();
        return StockItem::latest('id')->firstOrFail();
    }

    private function movement(StockItem $item, string $type, string $quantity, ?string $key = null): void
    {
        $this->post(route('stock.movements.store', $item), ['type' => $type, 'quantity' => $quantity,
            'reason' => 'Fictional movement', 'reference' => 'Fixture ref', 'request_key' => $key ?: (string) Str::uuid()])->assertRedirect();
    }

    private function createCount(StockItem ...$items): StockCount
    {
        $this->post(route('stock.counts.store'), ['title' => 'Fictional cycle count', 'item_ids' => collect($items)->pluck('id')->all()])
            ->assertRedirect()->assertSessionHasNoErrors();
        return StockCount::latest('id')->firstOrFail();
    }

    private function lines(StockCount $count, string $quantity, string $reason = ''): array
    {
        return $count->lines->mapWithKeys(fn ($line) => [$line->id => ['counted_qty' => $quantity, 'reason' => $reason]])->all();
    }

    public function test_opening_batch_identity_and_fixed_point_manual_ledger(): void
    {
        $item = $this->makeItem();
        $other = $this->makeItem('Amber 100ml', 'B-02', '0');
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $item->id, 'type' => 'opening']);
        $this->assertSame('10.125', $item->fresh()->quantity_on_hand);
        $this->assertSame('0.000', $other->fresh()->quantity_on_hand);
        $key = (string) Str::uuid();
        $this->movement($item, 'receipt', '0.100', $key);
        $this->movement($item, 'receipt', '0.100', $key);
        $this->movement($item, 'issue', '0.001');
        $this->assertSame('10.224', $item->fresh()->quantity_on_hand);
        $this->assertSame(3, $item->movements()->count(), 'A retried movement must not post twice.');
        $this->from(route('stock.items.show', $item))->post(route('stock.movements.store', $item), ['type' => 'issue',
            'quantity' => '11', 'reason' => 'Too much', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
        $this->assertSame('10.224', $item->fresh()->quantity_on_hand);
        $this->from(route('stock.items.create'))->post(route('stock.items.store'), ['product_id' => $this->product->id,
            'variant' => ' AMBER   100ML ', 'batch_code' => ' b-01 ', 'unit' => 'pcs', 'opening_quantity' => '0', 'reason' => 'Duplicate'])
            ->assertSessionHasErrors();
        $this->assertSame(2, StockItem::count());
    }

    public function test_binary_float_quantity_from_json_is_rejected_without_posting(): void
    {
        $response = $this->postJson(route('stock.items.store'), ['product_id' => $this->product->id, 'variant' => 'JSON',
            'unit' => 'pcs', 'opening_quantity' => 0.1, 'reason' => 'Do not round binary floats']);
        $this->assertSame(422, $response->getStatusCode(), 'A JSON stock request should receive JSON validation errors, not a redirect.');
        $response->assertJsonValidationErrors('opening_quantity');
        $this->assertSame(0, StockItem::count());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_blank_is_not_zero_and_independent_reviewer_posts_audited_variance_once(): void
    {
        $item = $this->makeItem();
        $count = $this->createCount($item);
        $line = $count->lines()->firstOrFail();
        $this->from(route('stock.counts.show', $count))->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision, 'lines' => [
            $line->id => ['counted_qty' => '', 'reason' => ''],
        ]])->assertSessionHasErrors();
        $this->from(route('stock.counts.show', $count))->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision, 'lines' => [
            $line->id => ['counted_qty' => '0', 'reason' => ''],
        ]])->assertSessionHasErrors();
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision, 'lines' => [$line->id => ['counted_qty' => '0', 'reason' => 'Empty shelf']]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Self approval'])->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Verified shelf and count sheet'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.000', $item->fresh()->quantity_on_hand);
        $this->assertSame('posted', $count->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $item->id, 'stock_count_line_id' => $line->id, 'type' => 'count_adjustment']);
        $this->assertDatabaseHas('stock_count_events', ['stock_count_id' => $count->id, 'action' => 'posted', 'actor_id' => $this->reviewer->id]);
        $this->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Retry'])->assertSessionHasErrors();
        $this->assertSame(1, DB::table('stock_movements')->where('stock_count_line_id', $line->id)->count());
    }

    public function test_changed_ledger_even_if_net_zero_blocks_post_until_reject_refresh_and_recount(): void
    {
        $item = $this->makeItem();
        $count = $this->createCount($item);
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision, 'lines' => $this->lines($count, '9', 'One damaged')])->assertRedirect();
        $this->movement($item, 'receipt', '1');
        $this->movement($item, 'issue', '1');
        $this->actingAs($this->reviewer)->from(route('stock.counts.show', $count))
            ->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Review'])->assertSessionHasErrors();
        $this->assertSame('submitted', $count->fresh()->status);
        $this->assertSame('10.125', $item->fresh()->quantity_on_hand);
        $this->post(route('stock.counts.reject', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Stock moved; recount'])->assertRedirect();
        $this->actingAs($this->counter)->post(route('stock.counts.refresh', $count), ['revision' => $count->fresh()->revision])->assertRedirect();
        $this->assertNull($count->fresh()->lines->first()->counted_qty);
        $this->assertDatabaseHas('stock_count_events', ['stock_count_id' => $count->id, 'action' => 'submitted']);
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision, 'lines' => $this->lines($count->fresh()->load('lines'), '10.125')])->assertRedirect();
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Recount agrees'])->assertRedirect();
        $this->assertSame('posted', $count->fresh()->status);
        $this->assertSame(3, $item->movements()->count(), 'A matching count must not create a zero adjustment.');
    }

    public function test_permissions_and_product_stock_history_are_protected(): void
    {
        $item = $this->makeItem();
        $reader = $this->user('reader', ['operations.view']);
        $this->actingAs($reader)->get(route('stock.index'))->assertOk()->assertSee('Fictional bottle');
        $this->post(route('stock.movements.store', $item), ['type' => 'issue', 'quantity' => '1', 'reason' => 'No access',
            'request_key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($this->counter)->delete(route('products.destroy', $this->product))->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
        $this->assertSame(1, $item->movements()->count());
        auth()->logout();
        $this->get(route('stock.index'))->assertRedirect(route('login'));
    }

    public function test_stale_draft_or_review_form_cannot_change_a_newer_count_revision(): void
    {
        $item = $this->makeItem();
        $count = $this->createCount($item);
        $draft = $count->revision;
        $this->post(route('stock.counts.update', $count), ['revision' => $draft, 'lines' => $this->lines($count, '9', 'Short')])->assertRedirect();
        $this->post(route('stock.counts.update', $count), ['revision' => $draft, 'lines' => $this->lines($count, '8', 'Old form')])->assertSessionHasErrors();
        $count->refresh()->load('lines');
        $this->assertSame('9.000', $count->lines->first()->counted_qty);
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->revision, 'lines' => $this->lines($count, '9', 'Short')])->assertRedirect();
        $oldReview = $count->fresh()->revision;
        $this->actingAs($this->reviewer)->post(route('stock.counts.reject', $count), ['revision' => $oldReview, 'reason' => 'Recheck'])->assertRedirect();
        $this->actingAs($this->counter)->post(route('stock.counts.submit', $count), ['revision' => $count->fresh()->revision,
            'lines' => $this->lines($count->fresh()->load('lines'), '8', 'Rechecked')])->assertRedirect();
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $oldReview, 'reason' => 'Stale review'])->assertSessionHasErrors();
        $this->assertSame('submitted', $count->fresh()->status);
        $this->assertSame('10.125', $item->fresh()->quantity_on_hand);
    }

    public function test_inconsistent_cached_balance_blocks_new_postings_and_count_snapshots(): void
    {
        $item = $this->makeItem();
        DB::table('stock_items')->where('id', $item->id)->update(['quantity_milli' => 99000]);
        $this->from(route('stock.items.show', $item))->post(route('stock.movements.store', $item), ['type' => 'receipt',
            'quantity' => '1', 'reason' => 'Should be blocked', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors();
        $this->from(route('stock.counts.create'))->post(route('stock.counts.store'), ['title' => 'Unsafe snapshot', 'item_ids' => [$item->id]])
            ->assertSessionHasErrors();
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame(0, StockCount::count());
    }

    public function test_populated_stock_migration_cannot_be_rolled_back(): void
    {
        $this->makeItem();
        try {
            (require database_path('migrations/2026_11_04_100000_create_stock_reconciliation_tables.php'))->down();
            $this->fail('A rollback must not discard stock history.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot roll back stock', $error->getMessage());
        }
        $this->assertSame(1, StockItem::count());
    }
}
