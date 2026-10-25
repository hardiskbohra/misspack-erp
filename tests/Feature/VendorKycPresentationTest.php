<?php

namespace Tests\Feature;

use App\Models\Client;
use Tests\Support\VendorKycFixture as F;
use Tests\TestCase;

class VendorKycPresentationTest extends TestCase
{
    public function test_actual_http_public_and_office_states_are_accessible_and_use_shared_components(): void
    {
        $f = F::install();
        $client = Client::create(['client_number' => 'KYC-CLIENT-REGRESSION', 'company_name' => 'Client KYC regression', 'status' => 'approved']);
        $this->capture('client-readonly', $this->get('/client-kyc/'.$client->public_token)->assertOk());
        $id = $f['vendor']->id;
        $base = '/vendors/'.$id.'/kyc';
        $this->actingAs($f['maker'])->post($base.'/link', ['action' => 'issue'])->assertRedirect();
        $url = '/vendor-kyc/'.$f['vendor']->fresh()->kyc_public_token;
        $this->capture('draft', $this->get($url)->assertOk()->assertSee('data-kyc-persist="0"', false));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post($url, F::payload(['intent' => 'submit', 'kyc_version' => $f['vendor']->fresh()->kyc_version, 'contact_person_email' => 'bad']))->assertSessionHasErrors('contact_person_email');
        $this->capture('errors', $this->get($url)->assertOk()->assertSee('data-kyc-error-step="2"', false));
        $this->post($url, F::payload(['intent' => 'submit', 'kyc_version' => $f['vendor']->fresh()->kyc_version]))->assertRedirect();
        $this->capture('under-review', $this->get($url)->assertOk()->assertDontSee('data-kyc-submit', false));
        $this->capture('office-maker', $this->get('/vendors/'.$id.'?tab=kyc')->assertOk()->assertSee('cannot approve their own vendor KYC'));
        $this->actingAs($f['reader']);
        $this->capture('office-reader', $this->get('/vendors/'.$id.'?tab=kyc')->assertOk()->assertDontSee('name="decision"', false));
        $this->actingAs($f['checker']);
        $this->capture('office-checker', $this->get('/vendors/'.$id.'?tab=kyc')->assertOk()->assertSee('name="decision"', false));
        $this->patch($base.'/review', ['decision' => 'revision', 'reason' => 'Please verify bank branch', 'kyc_version' => $f['vendor']->fresh()->kyc_version])->assertRedirect();
        $this->capture('revision', $this->get($url)->assertOk()->assertSee('Please verify bank branch'));
        $this->post($url, F::payload(['intent' => 'submit', 'kyc_version' => $f['vendor']->fresh()->kyc_version]))->assertRedirect();
        $this->patch($base.'/review', ['decision' => 'approved', 'kyc_version' => $f['vendor']->fresh()->kyc_version])->assertRedirect();
        $this->capture('approved', $this->get($url)->assertOk()->assertSee('Approved.')->assertDontSee('data-kyc-submit', false));
    }

    private function capture(string $name, $response): void
    {
        if (! getenv('VENDOR_KYC_BROWSER_FIXTURES')) {
            return;
        }
        $dir = base_path('.work/vendor-kyc');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$name.'.html',$response->getContent());
    }
}
