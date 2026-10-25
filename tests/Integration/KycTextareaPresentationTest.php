<?php

namespace Tests\Integration;

use Tests\Support\VendorKycFixture as F;
use Tests\TestCase;

class KycTextareaPresentationTest extends TestCase
{
    public function test_public_multiline_fields_share_input_base_focus_and_error_styles(): void
    {
        $f = F::install();
        $this->actingAs($f['maker'])->post('/vendors/'.$f['vendor']->id.'/kyc/link', ['action' => 'issue'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $page = $this->get('/vendor-kyc/'.$f['vendor']->fresh()->kyc_public_token)->assertOk()->assertSee('name="bank_address"', false)->assertSee('name="beneficiary_address"', false);
        if (getenv('MEDIA_BROWSER_FIXTURES')) {
            if (! is_dir(base_path('.work/media-avatar'))) {
                mkdir(base_path('.work/media-avatar'), 0777, true);
            }
            file_put_contents(base_path('.work/media-avatar/kyc.html'), $page->getContent());
        }
        $css = file_get_contents(public_path('assets/css/kyc-public.css'));
        foreach (['.kyc-field textarea {', '.kyc-field textarea:focus {', '.kyc-field.has-error textarea {', 'resize: vertical'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
    }
}
