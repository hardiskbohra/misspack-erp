<?php

namespace Tests\Feature;

use App\Models\HsnSacCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IT-TAX-007…010 — the Taxes & HSN settings area: the master is editable by
 * the office, guarded by its rules, and hands invoice forms the same list.
 */
class TaxMasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:' . base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_26_010000_create_hsn_sac_codes_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }

    private function office(): User
    {
        $user = User::create(['name' => 'Finance head', 'email' => 'finance@example.test',
            'password' => 'password', 'role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_migration_seeds_packaging_defaults_and_the_area_lists_them(): void
    {
        $this->office();

        $this->assertGreaterThanOrEqual(10, HsnSacCode::count());
        $this->assertDatabaseHas('hsn_sac_codes', ['code' => '3923', 'gst_rate' => 18.00]);

        $this->get(route('settings.taxes'))
            ->assertOk()
            ->assertSee('Taxes &amp; HSN', false)
            ->assertSee('3923')
            ->assertSee('settings.partials.nav', false);
    }

    public function test_add_edit_and_remove_a_code_through_the_settings_door(): void
    {
        $this->office();

        $this->post(route('settings.taxes.hsn.store'), [
            'code' => '39232100', 'kind' => 'goods', 'gst_rate' => 18, 'description' => 'Corrugated boxes of paper',
        ])->assertRedirect();
        $this->assertDatabaseHas('hsn_sac_codes', ['code' => '39232100', 'is_active' => 1]);

        $code = HsnSacCode::where('code', '39232100')->firstOrFail();
        $this->put(route('settings.taxes.hsn.update', $code), [
            'code' => '39232100', 'kind' => 'goods', 'description' => 'Corrugated boxes of paper',
            'gst_rate' => 12, 'sort_order' => 5,
        ])->assertRedirect();
        $this->assertEquals(12.0, $code->fresh()->gst_rate);
        $this->assertFalse($code->fresh()->is_active); // unchecked box means false, not "unchanged"

        $this->delete(route('settings.taxes.hsn.destroy', $code))->assertRedirect();
        $this->assertDatabaseMissing('hsn_sac_codes', ['code' => '39232100']);
    }

    public function test_the_rules_a_typo_breaks_against_all_fire(): void
    {
        $this->office();

        $this->post(route('settings.taxes.hsn.store'), [
            'code' => '39A3', 'kind' => 'goods', 'gst_rate' => 18, 'description' => 'Letters where digits belong',
        ])->assertSessionHasErrors('code');

        $this->post(route('settings.taxes.hsn.store'), [
            'code' => '3923', 'kind' => 'goods', 'gst_rate' => 18, 'description' => 'A duplicate of the seeded code',
        ])->assertSessionHasErrors('code');

        $this->post(route('settings.taxes.hsn.store'), [
            'code' => '99999999', 'kind' => 'goods', 'gst_rate' => 450, 'description' => 'Rate out of the possible range',
        ])->assertSessionHasErrors('gst_rate');
    }

    public function test_guests_cannot_touch_the_master(): void
    {
        $this->get(route('settings.taxes'))->assertRedirect();
        $this->post(route('settings.taxes.hsn.store'), [
            'code' => '1234', 'kind' => 'goods', 'gst_rate' => 5, 'description' => 'Sneaky',
        ])->assertRedirect();
        $this->assertDatabaseMissing('hsn_sac_codes', ['code' => '1234']);
    }
}
