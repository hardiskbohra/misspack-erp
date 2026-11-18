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

/** Disposable full-schema checks for stock corrections and reversible archive. */
class StockRegistryEditArchiveTest extends TestCase
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
        $this->counter = $this->makeUser('counter', ['operations.manage', 'sales.manage']);
        $this->reviewer = $this->makeUser('reviewer', ['operations.manage']);
        $this->product = Product::create(['product_number' => 'REG-P1', 'name' => 'Fictional register bottle', 'sku' => 'REG-SKU']);
        $this->actingAs($this->counter);
    }

    private function makeUser(string $name, array $permissions): User
    {
        return User::create(['name' => ucfirst($name), 'email' => $name.'@register.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
    }

    private function makeItem(string $variant = 'Amber', string $batch = 'LOT-A', string $quantity = '10.000'): StockItem
    {
        $this->post(route('stock.items.store'), ['product_id' => $this->product->id, 'variant' => $variant,
            'batch_code' => $batch, 'expires_on' => '2027-01-31', 'unit' => 'pcs',
            'opening_quantity' => $quantity, 'reason' => 'Opening stock'])->assertRedirect()->assertSessionHasNoErrors();
        return StockItem::latest('id')->firstOrFail();
    }

    private function editPayload(StockItem $item, array $overrides = []): array
    {
        $item->refresh();
        return array_replace(['product_id' => $item->product_id, 'variant' => $item->variant, 'batch_code' => $item->batch_code,
            'expires_on' => $item->expires_on?->format('Y-m-d'), 'unit' => $item->unit,
            'requested_quantity' => $item->quantity_on_hand, 'reason' => 'Correct a register entry',
            'record_revision' => $item->record_revision, 'ledger_version' => $item->ledger_version], $overrides);
    }

    private function archivePayload(StockItem $item, array $overrides = []): array
    {
        $item->refresh();
        return array_replace(['reason' => 'Remove duplicate entry from active register',
            'record_revision' => $item->record_revision, 'ledger_version' => $item->ledger_version,
            'acknowledge_nonzero' => '1'], $overrides);
    }

    public function test_details_are_audited_and_legacy_movements_keep_their_original_identity_and_unit(): void
    {
        $item = $this->makeItem();
        $other = Product::create(['product_number' => 'REG-P2', 'name' => 'Corrected product']);
        // Simulate a movement created before the snapshot column was introduced.
        DB::table('stock_movements')->where('stock_item_id', $item->id)->update(['item_snapshot' => null]);
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['product_id' => $other->id,
            'variant' => 'Blue', 'batch_code' => 'LOT-B', 'expires_on' => '2027-02-28', 'unit' => 'boxes']))
            ->assertRedirect(route('stock.items.show', $item))->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame($other->id, $item->product_id);
        $this->assertSame('Blue', $item->variant);
        $this->assertSame('LOT-B', $item->batch_code);
        $this->assertSame('boxes', $item->unit);
        $this->assertSame('10.000', $item->quantity_on_hand);
        $this->assertSame(1, $item->record_revision);
        $this->assertSame(1, $item->identity_revision);
        $movement = $item->movements()->firstOrFail();
        $this->assertSame('Fictional register bottle', $movement->item_snapshot['product_name']);
        $this->assertSame('Amber', $movement->item_snapshot['variant']);
        $this->assertSame('pcs', $movement->item_snapshot['unit']);
        $event = $item->events()->firstOrFail();
        $this->assertSame('updated', $event->action);
        $this->assertSame('Amber', $event->before_snapshot['variant']);
        $this->assertSame('Blue', $event->after_snapshot['variant']);
        $this->assertSame('Correct a register entry', $event->reason);
        $this->assertSame($this->counter->id, $event->actor_id);
        $this->assertDatabaseHas('stock_item_product_history', ['stock_item_id' => $item->id, 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('stock_item_product_history', ['stock_item_id' => $item->id, 'product_id' => $other->id]);
        $this->delete(route('products.destroy', $this->product))->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
        $this->get(route('stock.items.show', $item))->assertOk()->assertSee('Corrected product')->assertSee('pcs')->assertSee('Amber');
    }

    public function test_quantity_edit_is_an_independently_approved_count_not_a_direct_ledger_write(): void
    {
        $item = $this->makeItem();
        $oldForm = $this->editPayload($item, ['requested_quantity' => '8.500', 'reason' => 'Verified damaged stock']);
        $this->put(route('stock.items.update', $item), $oldForm)->assertRedirect()->assertSessionHasNoErrors();
        $count = StockCount::latest('id')->firstOrFail();
        $this->assertSame('submitted', $count->status);
        $this->assertSame($this->counter->id, $count->created_by);
        $this->assertSame('8.500', $count->lines()->firstOrFail()->counted_qty);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(1, $item->movements()->count());
        $this->put(route('stock.items.update', $item), $oldForm)->assertSessionHasErrors();
        $this->assertSame(1, StockCount::count(), 'Retrying an old form must not create a second count.');
        $this->post(route('stock.counts.approve', $count), ['revision' => $count->revision, 'reason' => 'Self approval'])->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $count->revision, 'reason' => 'Independent shelf review'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('8.500', $item->fresh()->quantity_on_hand);
        $this->assertSame(1, $item->movements()->where('type', 'count_adjustment')->count());
        $this->assertSame($count->id, $item->events()->firstOrFail()->stock_count_id);
    }

    public function test_combined_identity_and_balance_correction_snapshots_the_corrected_item_for_review(): void
    {
        $item = $this->makeItem();
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['variant' => 'Corrected', 'unit' => 'boxes',
            'requested_quantity' => '8.500', 'reason' => 'Correct identity and physical quantity']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $count = StockCount::latest('id')->firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->assertSame('Corrected', $item->fresh()->variant);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->assertSame('Corrected', $line->item_snapshot['variant']);
        $this->assertSame('boxes', $line->item_snapshot['unit']);
        $this->assertSame(1, $line->item_snapshot['identity_revision']);
        $this->assertSame('8.500', $line->counted_qty);
        $this->assertSame('Amber', $item->events()->firstOrFail()->before_snapshot['variant']);
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $count->revision, 'reason' => 'Evidence verified'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('8.500', $item->fresh()->quantity_on_hand);
        $this->assertSame('boxes', $item->movements()->firstOrFail()->item_snapshot['unit']);
    }

    public function test_duplicate_identity_stale_forms_and_float_quantities_do_not_mutate_the_register(): void
    {
        $item = $this->makeItem();
        $other = $this->makeItem('Blue', 'LOT-B');
        $this->from(route('stock.items.edit', $item))->put(route('stock.items.update', $item),
            $this->editPayload($item, ['variant' => ' BLUE ', 'batch_code' => 'lot-b']))->assertSessionHasErrors();
        $this->assertSame('Amber', $item->fresh()->variant);
        $this->assertSame(0, $item->events()->count());
        $stale = $this->editPayload($item, ['variant' => 'Outdated']);
        $this->post(route('stock.movements.store', $item), ['type' => 'receipt', 'quantity' => '1', 'reason' => 'New stock',
            'record_revision' => $item->fresh()->record_revision, 'request_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('stock.items.edit', $item))->put(route('stock.items.update', $item), $stale)
            ->assertRedirect(route('stock.items.edit', $item))->assertSessionHasErrors();
        $this->get(route('stock.items.edit', $item))->assertOk()->assertSee('View your unsaved entries from the older form')
            ->assertSee('Outdated')->assertSee('11.000');
        $this->assertSame('Amber', $item->fresh()->variant);
        $this->assertSame('11.000', $item->fresh()->quantity_on_hand);
        $this->putJson(route('stock.items.update', $other), $this->editPayload($other, ['requested_quantity' => 0.1]))
            ->assertStatus(422)->assertJsonValidationErrors('requested_quantity');
        $this->assertSame(0, StockCount::count());
    }

    public function test_unsaved_edit_remains_visible_if_the_item_was_archived_in_another_session(): void
    {
        $item = $this->makeItem();
        $edit = $this->editPayload($item, ['variant' => 'Unsaved variant', 'form_context' => 'edit']);
        $this->delete(route('stock.items.destroy', $item), $this->archivePayload($item))->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('stock.items.edit', $item))->put(route('stock.items.update', $item), $edit)
            ->assertRedirect(route('stock.items.edit', $item));
        $this->get(route('stock.items.edit', $item))->assertRedirect(route('stock.items.show', $item));
        $this->get(route('stock.items.show', $item))->assertOk()->assertSee('View your unsaved edit')
            ->assertSee('Unsaved variant');
        $this->assertSame('Amber', $item->fresh()->variant);
        $this->assertNotNull($item->fresh()->archived_at);
    }

    public function test_stale_movement_form_cannot_post_to_a_corrected_identity_and_preserves_unsaved_entries(): void
    {
        $item = $this->makeItem();
        $movement = ['type' => 'issue', 'quantity' => '2', 'reason' => 'Issue under the old identity',
            'record_revision' => $item->record_revision, 'request_key' => (string) Str::uuid(), 'form_context' => 'movement'];
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['variant' => 'Corrected', 'unit' => 'boxes']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('stock.items.show', $item))->post(route('stock.movements.store', $item), $movement)
            ->assertRedirect(route('stock.items.show', $item))->assertSessionHasErrors('stock');
        $this->get(route('stock.items.show', $item))->assertOk()->assertSee('View your unsaved movement')
            ->assertSee('Issue under the old identity')->assertSee('Corrected');
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(1, $item->movements()->count());
        unset($movement['record_revision']);
        $this->post(route('stock.movements.store', $item), $movement)->assertSessionHasErrors('record_revision');
        $this->assertSame(1, $item->movements()->count());
    }

    public function test_exact_movement_retry_remains_idempotent_after_identity_correction(): void
    {
        $item = $this->makeItem();
        $movement = ['type' => 'receipt', 'quantity' => '1', 'reason' => 'Original receipt',
            'record_revision' => $item->record_revision, 'request_key' => (string) Str::uuid()];
        $this->post(route('stock.movements.store', $item), $movement)->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['variant' => 'Corrected']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('stock.movements.store', $item), $movement)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('11.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(2, $item->movements()->count());
    }

    public function test_identity_edit_invalidates_an_open_count_even_when_the_balance_does_not_change(): void
    {
        $item = $this->makeItem();
        $this->post(route('stock.counts.store'), ['title' => 'Shelf count', 'item_ids' => [$item->id]])->assertRedirect()->assertSessionHasNoErrors();
        $count = StockCount::latest('id')->firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->revision,
            'lines' => [$line->id => ['counted_qty' => '9', 'reason' => 'Damaged']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['batch_code' => 'LOT-C']))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->from(route('stock.counts.show', $count))->post(route('stock.counts.approve', $count),
            ['revision' => $count->fresh()->revision, 'reason' => 'Old identity'])->assertSessionHasErrors();
        $this->assertSame('submitted', $count->fresh()->status);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->post(route('stock.counts.reject', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Identity corrected; recount'])->assertRedirect();
        $this->actingAs($this->counter)->post(route('stock.counts.refresh', $count), ['revision' => $count->fresh()->revision])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($count->fresh()->lines->first()->counted_qty);
        $this->assertSame('LOT-C', $count->fresh()->lines->first()->item_snapshot['batch_code']);
    }

    public function test_archive_warns_for_nonzero_stock_preserves_history_blocks_new_writes_and_can_be_restored(): void
    {
        $item = $this->makeItem();
        $this->from(route('stock.items.show', $item))->delete(route('stock.items.destroy', $item),
            $this->archivePayload($item, ['acknowledge_nonzero' => null]))->assertSessionHasErrors('acknowledge_nonzero');
        $this->delete(route('stock.items.destroy', $item), $this->archivePayload($item))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($item->fresh()->archived_at);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(1, $item->movements()->count());
        $this->get(route('stock.index'))->assertOk()->assertDontSee('Fictional register bottle')->assertSee('archived with stock');
        $this->get(route('stock.index', ['status' => 'archived']))->assertOk()->assertSee('Fictional register bottle')->assertSee('10.000');
        $this->post(route('stock.movements.store', $item), ['type' => 'issue', 'quantity' => '1', 'reason' => 'Archived',
            'record_revision' => $item->fresh()->record_revision, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('stock');
        $this->post(route('stock.counts.store'), ['title' => 'Archived item', 'item_ids' => [$item->id]])->assertSessionHasErrors();
        $this->post(route('stock.items.store'), ['product_id' => $this->product->id, 'variant' => 'Amber', 'batch_code' => 'LOT-A',
            'unit' => 'pcs', 'opening_quantity' => '0', 'reason' => 'Duplicate archived identity'])->assertSessionHasErrors();
        $this->post(route('stock.items.restore', $item), ['reason' => 'Return item to register',
            'record_revision' => $item->fresh()->record_revision, 'ledger_version' => $item->fresh()->ledger_version])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(['restored', 'archived'], $item->events()->pluck('action')->all());
    }

    public function test_archived_item_exposes_completed_count_history_even_without_a_ledger_adjustment(): void
    {
        $item = $this->makeItem();
        $this->post(route('stock.counts.store'), ['title' => 'Matching shelf count', 'item_ids' => [$item->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $count = StockCount::latest('id')->firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->post(route('stock.counts.submit', $count), ['revision' => $count->revision,
            'lines' => [$line->id => ['counted_qty' => '10.000', 'reason' => '']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->reviewer)->post(route('stock.counts.approve', $count), ['revision' => $count->fresh()->revision, 'reason' => 'Matched'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->counter)->delete(route('stock.items.destroy', $item), $this->archivePayload($item))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('stock.items.show', $item))->assertOk()->assertSee('Count history')->assertSee($count->number());
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame(1, $item->countLines()->count());
    }

    public function test_zero_balance_archive_needs_no_nonzero_acknowledgement_and_old_restore_cannot_repeat(): void
    {
        $item = $this->makeItem('Zero', 'LOT-Z', '0');
        $payload = $this->archivePayload($item);
        unset($payload['acknowledge_nonzero']);
        $this->delete(route('stock.items.destroy', $item), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $item->refresh();
        $restore = ['reason' => 'Keep in active register', 'record_revision' => $item->record_revision, 'ledger_version' => $item->ledger_version];
        $this->post(route('stock.items.restore', $item), $restore)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('stock.items.restore', $item), $restore)->assertSessionHasErrors('stock');
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame('0.000', $item->fresh()->quantity_on_hand);
        $this->assertSame(2, $item->events()->count());
    }

    public function test_an_archived_identity_remains_reserved_for_later_edits(): void
    {
        $archived = $this->makeItem();
        $other = $this->makeItem('Red', 'LOT-R', '0');
        $this->delete(route('stock.items.destroy', $archived), $this->archivePayload($archived))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('stock.items.update', $other), $this->editPayload($other, ['variant' => 'Amber', 'batch_code' => 'LOT-A']))
            ->assertSessionHasErrors('batch_code');
        $this->assertSame('Red', $other->fresh()->variant);
        $this->assertSame(0, $other->events()->count());
    }

    public function test_stale_archive_decision_rejects_a_new_balance_and_keeps_the_unapplied_reason(): void
    {
        $item = $this->makeItem();
        $decision = $this->archivePayload($item, ['form_context' => 'archive']);
        $this->post(route('stock.movements.store', $item), ['type' => 'receipt', 'quantity' => '1', 'reason' => 'New stock',
            'record_revision' => $item->record_revision, 'request_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('stock.items.show', $item))->delete(route('stock.items.destroy', $item), $decision)
            ->assertRedirect(route('stock.items.show', $item))->assertSessionHasErrors('stock');
        $this->get(route('stock.items.show', $item))->assertOk()->assertSee('Review the latest item and balance')
            ->assertSee('Unapplied reason: Remove duplicate entry from active register')->assertSee('11.000');
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame(0, $item->events()->count());
    }

    public function test_open_count_blocks_archive_and_readers_cannot_edit_or_archive(): void
    {
        $item = $this->makeItem();
        $this->post(route('stock.counts.store'), ['title' => 'Open count', 'item_ids' => [$item->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->delete(route('stock.items.destroy', $item), $this->archivePayload($item))->assertSessionHasErrors();
        $this->assertNull($item->fresh()->archived_at);
        $reader = $this->makeUser('reader', ['operations.view']);
        $this->actingAs($reader)->get(route('stock.items.show', $item))->assertOk();
        $this->get(route('stock.items.edit', $item))->assertForbidden();
        $this->put(route('stock.items.update', $item), $this->editPayload($item))->assertForbidden();
        $this->delete(route('stock.items.destroy', $item), $this->archivePayload($item))->assertForbidden();
        $this->post(route('stock.items.restore', $item), $this->archivePayload($item))->assertForbidden();
    }

    public function test_upgrade_preserves_existing_stock_and_backfills_product_references(): void
    {
        // Use only this test's isolated in-memory database to simulate an
        // installation that had stock before the edit/archive migration.
        $migration = require database_path('migrations/2026_11_04_110000_add_stock_register_edit_archive.php');
        $migration->down();
        $now = now();
        $id = DB::table('stock_items')->insertGetId(['product_id' => $this->product->id, 'variant' => 'Legacy',
            'batch_code' => 'LOT-OLD', 'unit' => 'pcs',
            'identity_hash' => \App\Services\StockLedger::identityHash($this->product->id, 'Legacy', 'LOT-OLD'),
            'quantity_milli' => 10000, 'ledger_version' => 1, 'created_by' => $this->counter->id,
            'creator_name' => $this->counter->name, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('stock_movements')->insert(['stock_item_id' => $id, 'type' => 'opening', 'delta_milli' => 10000,
            'balance_after_milli' => 10000, 'ledger_version' => 1, 'reason' => 'Legacy opening', 'request_key' => 'legacy:'.$id,
            'created_by' => $this->counter->id, 'actor_name' => $this->counter->name, 'created_at' => $now, 'updated_at' => $now]);
        $migration->up();
        $item = StockItem::findOrFail($id);
        $this->assertDatabaseHas('stock_item_product_history', ['stock_item_id' => $id, 'product_id' => $this->product->id]);
        $this->assertNull($item->movements()->firstOrFail()->item_snapshot);
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['variant' => 'Corrected']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $legacy = $item->movements()->firstOrFail()->item_snapshot;
        $this->assertSame('Legacy', $legacy['variant']);
        $this->assertSame('legacy_pre_edit', $legacy['snapshot_basis']);
        $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
    }

    public function test_populated_edit_archive_migration_refuses_to_discard_history(): void
    {
        $item = $this->makeItem();
        $this->put(route('stock.items.update', $item), $this->editPayload($item, ['variant' => 'Corrected']))->assertRedirect()->assertSessionHasNoErrors();
        try {
            (require database_path('migrations/2026_11_04_110000_add_stock_register_edit_archive.php'))->down();
            $this->fail('A rollback must not discard stock identity or archive history.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot roll back stock register', $error->getMessage());
        }
        $this->assertSame('Corrected', $item->fresh()->variant);
        $this->assertSame(1, $item->events()->count());
    }
}
