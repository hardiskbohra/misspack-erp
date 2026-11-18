<?php

namespace Tests\Feature;

use App\Models\OfficeAlert;
use App\Models\OfficeAlertState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IT-BRIEF-004…008 — the notification centre: the bell's desk as a page.
 *
 * The dropdown is the glance (open items only, whatever pops up); the centre
 * is the record — open, snoozed and resolved, filtered by desk and severity,
 * with who-cleared-what kept on the line. These tests pin the page the other
 * way a KYC brief is provable afterwards: "somebody saw it, and here is who".
 */
class OfficeAlertCentreTest extends TestCase
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
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_10_06_020000_create_office_alerts_table',
            '2026_10_06_030000_add_shared_ack_to_office_alerts',
            '2026_10_06_040000_add_snooze_and_email_to_office_alerts',
            '2026_10_06_050000_create_office_settings_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }

    private function office(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Office head', 'email' => 'office@example.test',
            'password' => 'password', 'role' => 'admin',
            'access_profile' => 'full', 'department' => 'management',
        ], $attributes));
        $this->actingAs($user);

        return $user;
    }

    private function alert(array $attributes = []): OfficeAlert
    {
        static $n = 0;
        $n++;

        return OfficeAlert::create(array_merge([
            'event_key' => 'test.event',
            'fingerprint' => 'test.event:' . $n,
            'title' => 'Test briefing ' . $n,
            'body' => 'Body for briefing ' . $n,
            'severity' => OfficeAlert::SEVERITY_CRITICAL,
            'requires_ack' => true,
            'team' => 'operations',
            'action_url' => '/dashboard', 'action_label' => 'Open',
        ], $attributes));
    }

    /** The alert cards region — the topbar bell embeds the same titles in its
        JSON payload, so page-wide See/DontSee assertions lie; scope to the
        centre list itself (markers are invisible comments in the blade). */
    private function centreCards(string $html): string
    {
        $start = strpos($html, 'obc-items-start');
        $end = strpos($html, 'obc-items-end');

        return $start === false || $end === false ? '' : substr($html, $start, $end - $start);
    }

    public function test_the_centre_is_locked_to_guests_and_the_payroll(): void
    {
        $this->get(route('office-alerts.centre'))->assertRedirect(route('login'));

        /* the `office` middleware sends payroll users to their own workspace */
        $employee = User::create(['name' => 'Hands', 'email' => 'hands@example.test',
            'password' => 'password', 'role' => 'employee', 'access_profile' => 'full']);
        $this->actingAs($employee);
        $this->get(route('office-alerts.centre'))->assertRedirect(route('my.dashboard'));
    }

    public function test_the_centre_lists_open_briefings_with_their_actions(): void
    {
        $this->office();
        $this->alert(['title' => 'SHP-1012 is on custom hold', 'team' => 'operations']);
        $this->alert(['title' => 'Client KYC waiting', 'team' => 'sales', 'severity' => OfficeAlert::SEVERITY_ATTENTION]);

        $response = $this->get(route('office-alerts.centre'));
        $response->assertOk()
            ->assertSee('Notification centre');
        $cards = $this->centreCards($response->getContent());
        $this->assertStringContainsString('SHP-1012 is on custom hold', $cards)
            && $this->assertStringContainsString('Client KYC waiting', $cards);
        $response->assertSee('Open', false)             // tray chip
            ->assertSee('settings/briefings', false);

        /* critical sorts above attention even though it was raised first */
        $critical = strpos($cards, 'SHP-1012');
        $attention = strpos($cards, 'Client KYC waiting');
        $this->assertLessThan($attention, $critical);
    }

    public function test_filters_narrow_the_tray_and_keep_the_other_filters(): void
    {
        $this->office();
        $this->alert(['title' => 'OPS-CASE', 'team' => 'operations']);
        $this->alert(['title' => 'SALES-CASE', 'team' => 'sales', 'severity' => OfficeAlert::SEVERITY_INFO, 'requires_ack' => false]);

        $cards = $this->centreCards($this->get(route('office-alerts.centre', ['team' => 'sales']))->assertOk()->getContent());
        $this->assertStringContainsString('SALES-CASE', $cards);
        $this->assertStringNotContainsString('OPS-CASE', $cards);

        $cards = $this->centreCards($this->get(route('office-alerts.centre', ['severity' => 'critical']))->assertOk()->getContent());
        $this->assertStringContainsString('OPS-CASE', $cards);
        $this->assertStringNotContainsString('SALES-CASE', $cards);

        /* a nonsense filter value is refused, not silently dropped */
        $this->get(route('office-alerts.centre', ['state' => 'purple']))->assertSessionHasErrors(['state']);

        /* the filter chips carry the SIBLING filters across: changing the
           desk never drops the severity, and changing the tray never drops
           the desk (the desk row's own chips legitimately re-target desk) */
        $response = $this->get(route('office-alerts.centre', ['team' => 'operations', 'severity' => 'critical']));
        preg_match_all('/href="([^"]+)"/', $response->getContent(), $href);
        $deskRow = array_filter($href[1], fn ($u) => str_contains($u, 'team=sales'));
        $this->assertNotEmpty($deskRow);
        foreach ($deskRow as $u) {
            $this->assertStringContainsString('severity=critical', $u);
        }
        $trayRow = array_filter($href[1], fn ($u) => str_contains($u, 'state=resolved'));
        $this->assertNotEmpty($trayRow);
        foreach ($trayRow as $u) {
            $this->assertStringContainsString('team=operations', $u);
        }
        $this->assertDatabaseCount('office_alert_states', 0); // a filter never mutates reader state
    }

    public function test_ack_from_the_centre_redirects_and_files_the_resolved_trail(): void
    {
        $user = $this->office();
        $alert = $this->alert(['title' => 'KYC-TEAM-LEAD']);

        $this->from(route('office-alerts.centre'))
            ->patch(route('office-alerts.ack', $alert))
            ->assertRedirect(route('office-alerts.centre'))
            ->assertSessionHas('status');

        $alert->refresh();
        $this->assertNotNull($alert->acked_at);
        $this->assertSame($user->id, $alert->acked_by);

        /* the open tray no longer shows it… */
        $cards = $this->centreCards($this->get(route('office-alerts.centre'))->getContent());
        $this->assertStringNotContainsString('KYC-TEAM-LEAD', $cards);

        /* …the resolved tray keeps it and names who acted */
        $cards = $this->centreCards($this->get(route('office-alerts.centre', ['state' => 'resolved']))->assertOk()->getContent());
        $this->assertStringContainsString('KYC-TEAM-LEAD', $cards);
        $this->assertStringContainsString('Office head', $cards);
        $this->assertStringContainsString('Resolved', $cards);
    }

    public function test_snooze_moves_the_card_out_of_open_and_back(): void
    {
        $user = $this->office();
        $alert = $this->alert(['title' => 'SNOOZE-ME']);

        $this->patch(route('office-alerts.snooze', $alert), ['until' => '1h'])
            ->assertRedirect();

        $this->assertDatabaseHas('office_alert_states', [
            'office_alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $cards = $this->centreCards($this->get(route('office-alerts.centre'))->getContent());
        $this->assertStringNotContainsString('SNOOZE-ME', $cards);
        $cards = $this->centreCards($this->get(route('office-alerts.centre', ['state' => 'snoozed']))->assertOk()->getContent());
        $this->assertStringContainsString('SNOOZE-ME', $cards);
    }

    public function test_a_desk_watcher_sees_only_their_desks_briefings(): void
    {
        $this->office(['department' => 'sales & marketing']);
        $this->alert(['title' => 'OPS-ONLY-BRIEF', 'team' => 'operations']);
        $this->alert(['title' => 'SALES-BRIEF', 'team' => 'sales']);
        $this->alert(['title' => 'ALL-HANDS-BRIEF', 'team' => null]);

        $cards = $this->centreCards($this->get(route('office-alerts.centre'))->assertOk()->getContent());
        $this->assertStringContainsString('SALES-BRIEF', $cards);
        $this->assertStringContainsString('ALL-HANDS-BRIEF', $cards);
        $this->assertStringNotContainsString('OPS-ONLY-BRIEF', $cards);

        /* the sales desk filter on an operations alert returns the empty state,
           not another desk's work */
        $this->get(route('office-alerts.centre', ['team' => 'operations']))
            ->assertOk()->assertSee('Nothing waiting');
    }
}
