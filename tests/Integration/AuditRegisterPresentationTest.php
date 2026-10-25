<?php

namespace Tests\Integration;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Tests\Support\AnalyticsDatabaseFixture;
use Tests\TestCase;

class AuditRegisterPresentationTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        AnalyticsDatabaseFixture::install();
        $user = User::create(['name' => 'Audit reader', 'email' => 'reader@audit.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['audit.view']]);
        $this->actingAs($user);
        for ($i = 1; $i <= 35; $i++) {
            $this->ids[] = AuditLog::create(['event' => 'fixture.changed', 'outcome' => 'success', 'actor_type' => 'office_user',
                'actor_name' => 'Example operator', 'actor_identifier' => 'operator@example.test', 'auditable_type' => Vendor::class, 'auditable_id' => $i,
                'route_name' => 'vendors.kyc.review', 'method' => 'PATCH', 'url' => 'https://erp.test/vendors/'.$i.'/kyc/review',
                'response_status' => 302, 'ip_address' => '192.0.2.1', 'user_agent' => 'Fixture browser '.$i,
                'changed_fields' => ['reason', 'decision'], 'context' => ['record' => $i, 'detail' => str_repeat('Long evidence ', 100)], 'occurred_at' => '2026-10-25 12:00:00'])->id;
        }
    }

    private function scope(): array
    {
        return ['search' => 'Example operator', 'event' => 'fixture.changed', 'outcome' => 'success', 'actor_type' => 'office_user', 'from' => '2026-10-01', 'to' => '2026-10-31'];
    }

    private function capture(string $name, $response): void
    {
        if (! getenv('AUDIT_BROWSER_FIXTURES')) {
            return;
        }
        $dir = base_path('.work/audit-register');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$name.'.html', $response->getContent());
    }

    public function test_shared_pagination_search_and_single_readonly_evidence_modal_replace_inline_popovers(): void
    {
        $before = AuditLog::count();
        $response = $this->get('/audit?'.http_build_query($this->scope()))->assertOk()
            ->assertSee('custom-pagination')->assertSee('id="auditEvidenceModal"', false)
            ->assertSee('data-audit-evidence', false)->assertDontSee('<details', false)
            ->assertSee('data-audit-search', false)->assertSee('audit-register.js');
        $this->assertSame(1, substr_count($response->getContent(), 'id="auditEvidenceModal"'));
        $this->assertSame($before, AuditLog::count());
        $this->capture('page1', $response);
    }

    public function test_pagination_keeps_scope_and_only_current_page_evidence_is_rendered(): void
    {
        $response = $this->get('/audit?'.http_build_query($this->scope() + ['page' => 2]))->assertOk()
            ->assertSee('data-audit-template="audit-evidence-'.$this->ids[4].'"', false)
            ->assertDontSee('data-audit-template="audit-evidence-'.$this->ids[34].'"', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//ul[contains(@class,"custom-pagination")]//a[@href]');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
            foreach ($this->scope() as $key => $value) {
                $this->assertSame($value, $params[$key] ?? null);
            }
        }
        $this->capture('page2', $response);
    }

    public function test_evidence_is_escaped_and_empty_results_keep_the_shared_readonly_shell(): void
    {
        $evil = '</template><img src=x onerror="window.auditXss=1">';
        AuditLog::create(['event' => 'fixture.changed', 'outcome' => 'failed', 'actor_type' => 'system', 'actor_name' => $evil,
            'method' => 'PATCH', 'url' => $evil, 'user_agent' => $evil, 'context' => ['unsafe' => $evil], 'occurred_at' => '2026-10-26 12:00:00']);
        $response = $this->get('/audit')->assertOk()->assertDontSee($evil, false)->assertSee('&lt;/template&gt;', false)->assertSee('id="auditEvidenceModal"', false);
        $this->capture('hostile', $response);
        $empty = $this->get('/audit?event=does.not.exist')->assertOk()->assertSee('No audit events match these filters.')->assertDontSee('data-audit-evidence=', false);
        $this->capture('empty', $empty);
    }

    public function test_non_audit_office_users_cannot_read_the_register_or_evidence(): void
    {
        $user = User::create(['name' => 'Purchase only', 'email' => 'purchase@audit.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['purchase.manage']]);
        $this->actingAs($user)->get('/audit')->assertForbidden()->assertDontSee('Fixture browser');
    }
}
