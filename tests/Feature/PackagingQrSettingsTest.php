<?php

namespace Tests\Feature;

use App\Models\OfficeSetting;
use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use App\Models\User;
use App\Services\PackagingLabelQr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackagingQrSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_10_06_050000_create_office_settings_table',
            '2026_10_06_060000_create_organisation_tables',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    private function office(array $attributes = []): void
    {
        $this->actingAs(User::create(array_merge([
            'name' => 'Settings officer', 'email' => 'settings@example.test', 'password' => 'password', 'role' => 'admin',
        ], $attributes)));
    }

    private function labels(string $format = 'thermal', int $copies = 1, string $sender = 'MissPack'): string
    {
        $list = new PackagingList(['packing_number' => 'PL-024', 'total_cartons' => 3,
            'from_name' => $sender, 'ship_to_name' => 'Customer', 'transport_mode' => 'road']);
        $list->id = 24;
        $carton = new PackagingListCarton(['carton_type' => 'box', 'barcode' => 'CARTON-024', 'carton_count' => 3]);
        $carton->id = 8;
        $carton->setRelation('packagingList', $list);
        $carton->setRelation('items', new Collection([new PackagingListItem([
            'item_description' => 'Bottle', 'quantity_per_carton' => 100, 'unit' => 'pcs',
        ])]));

        return view('packaging_lists.label', [
            'carton' => $carton, 'format' => $format, 'copies' => $copies, 'startCartonNumber' => 1,
            'organisation' => new Organisation(['legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack']),
        ])->render();
    }

    public function test_default_setting_preserves_the_existing_carton_specific_erp_link(): void
    {
        $this->assertSame(['label_qr_url' => ''], OfficeSetting::packaging());
        $this->assertSame('', PackagingLabelQr::configuredUrl());
        $html = $this->labels();
        $this->assertStringContainsString('data-ship-qr="'.route('packaging-lists.show', 24).'#carton-8"', $html);
    }

    public function test_qr_url_is_saved_and_findable_in_the_global_settings_hub_and_area(): void
    {
        $this->office();
        $url = 'https://example.test/packaging?source=carton&lang=en#details';
        $this->put(route('settings.packaging.update'), ['label_qr_url' => $url])
            ->assertRedirect(route('settings.packaging'))->assertSessionHasNoErrors();
        $this->assertSame($url, OfficeSetting::packaging()['label_qr_url']);
        $this->get(route('settings.packaging'))->assertOk()->assertSee('QR code link')->assertSee(e($url), false);
        $this->get(route('settings.index'))->assertOk()->assertSee(route('settings.packaging'), false);
    }

    public function test_every_format_and_sender_uses_the_exact_configured_destination_including_copies(): void
    {
        $url = 'https://example.test/packaging?source=carton&lang=en#details';
        OfficeSetting::putPackaging(['label_qr_url' => $url]);
        foreach (['a4', 'a5', 'thermal'] as $format) {
            foreach (['MissPack', 'Vendor Packaging Ltd'] as $sender) {
                $html = $this->labels($format, 3, $sender);
                $this->assertSame(3, substr_count($html, 'data-ship-qr="'.e($url).'"'));
                $this->assertStringNotContainsString('#carton-8', $html);
                $this->assertSame(3, substr_count($html, 'CARTON-024</small>'));
                $this->assertStringContainsString('<strong>3/3</strong>', $html);
            }
        }
    }

    public function test_setting_is_read_once_per_label_page_not_per_printed_copy(): void
    {
        OfficeSetting::putPackaging(['label_qr_url' => 'https://example.test/scan']);
        DB::enableQueryLog();
        $this->labels('thermal', 3);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "office_settings"'));
        $this->assertCount(1, $queries);
        DB::disableQueryLog();
    }

    public function test_blank_restores_the_default_and_preserves_other_global_settings(): void
    {
        $this->office();
        OfficeSetting::putBriefings(['enabled' => false]);
        OfficeSetting::putPackaging(['label_qr_url' => 'https://example.test/old']);
        $this->put(route('settings.packaging.update'), ['label_qr_url' => ''])
            ->assertRedirect(route('settings.packaging'))->assertSessionHasNoErrors();
        $this->assertSame('', PackagingLabelQr::configuredUrl());
        $this->assertFalse(OfficeSetting::briefings()['enabled']);
        $this->assertStringContainsString('#carton-8', $this->labels());
    }

    public function test_invalid_unsafe_or_oversized_urls_are_rejected_without_overwriting_the_setting(): void
    {
        $this->office();
        $original = 'https://example.test/original';
        OfficeSetting::putPackaging(['label_qr_url' => $original]);
        foreach ([
            'javascript:alert(1)', 'data:text/html,test', 'ftp://example.test/files', '//example.test/relative',
            '/local/path', 'not a URL', 'https://user:password@example.test/private',
            'https://example.test/'.str_repeat('x', 201),
            'https://example.test/'.str_repeat('界', 70), ['not-a-string'],
        ] as $url) {
            $this->from(route('settings.packaging'))->put(route('settings.packaging.update'), ['label_qr_url' => $url])
                ->assertRedirect(route('settings.packaging'))->assertSessionHasErrors('label_qr_url');
            $this->assertSame($original, PackagingLabelQr::configuredUrl());
        }
    }

    public function test_http_is_allowed_and_the_200_byte_limit_is_enforced(): void
    {
        $url = 'http://example.test/'.str_repeat('a', 200 - strlen('http://example.test/'));
        $this->assertTrue(PackagingLabelQr::validUrl($url));
        $this->assertFalse(PackagingLabelQr::validUrl($url.'a'));
    }

    public function test_invalid_manually_stored_values_fall_back_to_the_erp_link(): void
    {
        foreach (['javascript:alert(1)', ['bad'], 'https://user:pass@example.test/'] as $invalid) {
            OfficeSetting::putPackaging(['label_qr_url' => $invalid]);
            $this->assertSame('', PackagingLabelQr::configuredUrl());
            $this->assertStringContainsString('#carton-8', $this->labels());
        }
    }

    public function test_unmigrated_settings_table_keeps_existing_label_generation_working(): void
    {
        Schema::drop('office_settings');
        $this->assertSame('', PackagingLabelQr::configuredUrl());
        $this->assertStringContainsString('#carton-8', $this->labels());
    }

    public function test_operations_access_alone_does_not_allow_changing_global_settings(): void
    {
        $this->office(['access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $this->get(route('settings.packaging'))->assertForbidden();
        $this->put(route('settings.packaging.update'), ['label_qr_url' => 'https://example.test/denied'])->assertForbidden();
        $this->assertSame('', PackagingLabelQr::configuredUrl());
    }

    public function test_settings_manager_can_configure_the_global_destination(): void
    {
        $this->office(['access_profile' => 'custom', 'permissions' => ['settings.manage']]);
        $this->get(route('settings.packaging'))->assertOk();
        $this->put(route('settings.packaging.update'), ['label_qr_url' => 'https://example.test/managed'])
            ->assertRedirect(route('settings.packaging'));
        $this->assertSame('https://example.test/managed', PackagingLabelQr::configuredUrl());
    }

    public function test_saving_global_settings_requires_a_csrf_token(): void
    {
        $this->office();
        $this->app->detectEnvironment(fn () => 'local');
        $this->put(route('settings.packaging.update'), ['label_qr_url' => 'https://example.test/denied'])->assertStatus(419);
        $this->assertSame('', PackagingLabelQr::configuredUrl());
    }

    public function test_packaging_qr_settings_are_findable_by_search(): void
    {
        foreach (['QR code', 'carton', 'packaging'] as $query) {
            $hits = app(\App\Services\SettingsDirectory::class)->searchHits($query);
            $this->assertContains(route('settings.packaging'), array_column($hits, 'url'));
        }
    }

    public function test_login_is_required_for_settings(): void
    {
        $this->get(route('settings.packaging'))->assertRedirect(route('login'));
        $this->put(route('settings.packaging.update'), ['label_qr_url' => 'https://example.test/denied'])
            ->assertRedirect(route('login'));
        $this->assertSame('', PackagingLabelQr::configuredUrl());
    }
}
