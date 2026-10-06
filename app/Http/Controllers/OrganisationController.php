<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationAddress;
use App\Models\OrganisationBank;
use App\Models\OrganisationContact;
use App\Models\OrganisationSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganisationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'company');
        if (! in_array($tab, ['company', 'addresses', 'contacts', 'socials', 'banks'], true)) {
            $tab = 'company';
        }

        $organisation = Organisation::current();
        if (! $organisation->exists) {
            $organisation = Organisation::query()->create(Organisation::fallback());
        }

        $organisation->load(['addresses', 'banks', 'contacts', 'socials']);

        return view('organisation.settings', [
            'organisation' => $organisation,
            'tab' => $tab,
            'kinds' => OrganisationAddress::KINDS,
            'departments' => OrganisationContact::DEPARTMENTS,
            'networks' => OrganisationSocial::NETWORKS,
            'counts' => [
                'addresses' => $organisation->addresses->count(),
                'contacts' => $organisation->contacts->count(),
                'socials' => $organisation->socials->count(),
                'banks' => $organisation->banks->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organisation = $this->record();

        $data = $request->validate([
            'trade_name' => ['required', 'string', 'max:120'],
            'legal_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:160'],
            'website_url' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'cin' => ['nullable', 'string', 'max:30'],
            'iec' => ['nullable', 'string', 'max:20'],
            'msme' => ['nullable', 'string', 'max:40'],
            'lut' => ['nullable', 'string', 'max:40'],
            'jurisdiction_city' => ['nullable', 'string', 'max:80'],
            'jurisdiction_state' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'logo_print' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($organisation->logo_path) {
                Storage::disk('public')->delete($organisation->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('organisation', 'public');
        }

        if ($request->hasFile('logo_print')) {
            if ($organisation->logo_print_path) {
                Storage::disk('public')->delete($organisation->logo_print_path);
            }
            $data['logo_print_path'] = $request->file('logo_print')->store('organisation', 'public');
        }

        if ($request->boolean('remove_logo') && $organisation->logo_path) {
            Storage::disk('public')->delete($organisation->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->boolean('remove_logo_print') && $organisation->logo_print_path) {
            Storage::disk('public')->delete($organisation->logo_print_path);
            $data['logo_print_path'] = null;
        }

        unset($data['logo'], $data['logo_print']);
        $organisation->update($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'company'])
            ->with('success', 'Organisation profile saved.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $organisation = $this->record();
        $data = $this->addressData($request);
        $data['organisation_id'] = $organisation->id;
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            OrganisationAddress::query()
                ->where('organisation_id', $organisation->id)
                ->where('kind', $data['kind'])
                ->update(['is_default' => false]);
        }

        OrganisationAddress::create($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'addresses'])
            ->with('success', 'Address added.');
    }

    public function updateAddress(Request $request, OrganisationAddress $address): RedirectResponse
    {
        $data = $this->addressData($request);
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            OrganisationAddress::query()
                ->where('organisation_id', $address->organisation_id)
                ->where('kind', $data['kind'])
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'addresses'])
            ->with('success', 'Address saved.');
    }

    public function destroyAddress(OrganisationAddress $address): RedirectResponse
    {
        $address->delete();

        return redirect()
            ->route('organisation.settings', ['tab' => 'addresses'])
            ->with('success', 'Address removed.');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $organisation = $this->record();
        $data = $this->contactData($request);
        $data['organisation_id'] = $organisation->id;
        $data['is_primary'] = $request->boolean('is_primary');

        if ($data['is_primary']) {
            OrganisationContact::query()
                ->where('organisation_id', $organisation->id)
                ->where('department', $data['department'])
                ->update(['is_primary' => false]);
        }

        OrganisationContact::create($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'contacts'])
            ->with('success', 'Contact added.');
    }

    public function updateContact(Request $request, OrganisationContact $contact): RedirectResponse
    {
        $data = $this->contactData($request);
        $data['is_primary'] = $request->boolean('is_primary');

        if ($data['is_primary']) {
            OrganisationContact::query()
                ->where('organisation_id', $contact->organisation_id)
                ->where('department', $data['department'])
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'contacts'])
            ->with('success', 'Contact saved.');
    }

    public function destroyContact(OrganisationContact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()
            ->route('organisation.settings', ['tab' => 'contacts'])
            ->with('success', 'Contact removed.');
    }

    public function storeSocial(Request $request): RedirectResponse
    {
        $organisation = $this->record();
        $data = $this->socialData($request);
        $data['organisation_id'] = $organisation->id;
        OrganisationSocial::create($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'socials'])
            ->with('success', 'Social link added.');
    }

    public function updateSocial(Request $request, OrganisationSocial $social): RedirectResponse
    {
        $social->update($this->socialData($request));

        return redirect()
            ->route('organisation.settings', ['tab' => 'socials'])
            ->with('success', 'Social link saved.');
    }

    public function destroySocial(OrganisationSocial $social): RedirectResponse
    {
        $social->delete();

        return redirect()
            ->route('organisation.settings', ['tab' => 'socials'])
            ->with('success', 'Social link removed.');
    }

    public function storeBank(Request $request): RedirectResponse
    {
        $organisation = $this->record();
        $data = $this->bankData($request);
        $data['organisation_id'] = $organisation->id;
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            OrganisationBank::query()
                ->where('organisation_id', $organisation->id)
                ->update(['is_default' => false]);
        }

        OrganisationBank::create($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'banks'])
            ->with('success', 'Bank account added.');
    }

    public function updateBank(Request $request, OrganisationBank $bank): RedirectResponse
    {
        $data = $this->bankData($request);
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            OrganisationBank::query()
                ->where('organisation_id', $bank->organisation_id)
                ->where('id', '!=', $bank->id)
                ->update(['is_default' => false]);
        }

        $bank->update($data);

        return redirect()
            ->route('organisation.settings', ['tab' => 'banks'])
            ->with('success', 'Bank account saved.');
    }

    public function destroyBank(OrganisationBank $bank): RedirectResponse
    {
        $bank->delete();

        return redirect()
            ->route('organisation.settings', ['tab' => 'banks'])
            ->with('success', 'Bank account removed.');
    }

    private function record(): Organisation
    {
        $organisation = Organisation::query()->first();
        if (! $organisation) {
            $organisation = Organisation::query()->create(Organisation::fallback());
        }

        return $organisation;
    }

    private function addressData(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(OrganisationAddress::KINDS))],
            'label' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:16'],
        ]);
    }

    private function contactData(Request $request): array
    {
        return $request->validate([
            'department' => ['required', Rule::in(array_keys(OrganisationContact::DEPARTMENTS))],
            'name' => ['required', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:40'],
        ]);
    }

    private function socialData(Request $request): array
    {
        return $request->validate([
            'network' => ['required', Rule::in(array_keys(OrganisationSocial::NETWORKS))],
            'handle' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:255'],
        ]);
    }

    private function bankData(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'bank_name' => ['required', 'string', 'max:160'],
            'account_holder' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:40'],
            'ifsc' => ['nullable', 'string', 'max:20'],
            'branch' => ['nullable', 'string', 'max:80'],
            'swift' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
