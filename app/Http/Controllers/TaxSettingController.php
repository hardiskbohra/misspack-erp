<?php

namespace App\Http\Controllers;

use App\Models\HsnSacCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings · Taxes & HSN — the office's own HSN/SAC rate master.
 *
 * Per the settings module's boundary rule this controller owns its area's
 * validation and writes; `SettingsDirectory` only knows that the area exists
 * and what the hub card should say about it.
 */
class TaxSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.taxes', [
            'codes' => HsnSacCode::query()->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function storeHsn(Request $request): RedirectResponse
    {
        HsnSacCode::create($this->validated($request));

        return back()->with('status', 'HSN/SAC code saved. Invoice forms will offer it from now.');
    }

    public function updateHsn(Request $request, HsnSacCode $hsnSacCode): RedirectResponse
    {
        $hsnSacCode->update($this->validated($request, $hsnSacCode));

        return back()->with('status', 'HSN/SAC code updated. Existing invoice lines keep the rate they were saved with.');
    }

    public function destroyHsn(HsnSacCode $hsnSacCode): RedirectResponse
    {
        $hsnSacCode->delete();

        return back()->with('status', 'HSN/SAC code removed from the master.');
    }

    /**
     * One rule set for add and edit alike: the code is digits-only, 4–8 long
     * (the depth the e-invoice law requires), the rate is a plain percent in
     * the range GST actually uses.
     */
    private function validated(Request $request, ?HsnSacCode $editing = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'regex:/^\d{4,8}$/',
                Rule::unique('hsn_sac_codes', 'code')->ignore($editing?->id),
            ],
            'kind' => ['required', Rule::in(['goods', 'service'])],
            'description' => ['required', 'string', 'max:255'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'code.regex' => 'An HSN/SAC code is 4 to 8 digits (e.g. 3923 or 39232100).',
            'gst_rate.max' => 'GST rates do not go past 99.99% — check the decimal point.',
        ]);

        return [
            'code' => $data['code'],
            'kind' => $data['kind'],
            'description' => $data['description'],
            'gst_rate' => (float) $data['gst_rate'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
