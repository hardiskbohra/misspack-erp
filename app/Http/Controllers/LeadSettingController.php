<?php

namespace App\Http\Controllers;

use App\Models\LeadMasterOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadSettingController extends Controller
{
    public function index(Request $request): View
    {
        $groupOptions = LeadMasterOption::groupOptions();
        $activeTab = $request->query('tab', 'lead_status');

        if (! array_key_exists($activeTab, $groupOptions)) {
            $activeTab = 'lead_status';
        }

        return view('leads.settings', [
            'activeTab' => $activeTab,
            'groupOptions' => $groupOptions,
            'options' => LeadMasterOption::query()
                ->where('group', $activeTab)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['key'] = $this->normalizeKey($data['group'], $data['key']);
        $data['sort_order'] = $data['sort_order'] ?? 10;
        $data['is_active'] = $request->boolean('is_active', true);

        LeadMasterOption::create($data);

        return redirect()
            ->route('leads.settings.index', ['tab' => $data['group']])
            ->with('success', 'Lead master option created successfully.');
    }

    public function update(Request $request, LeadMasterOption $option): RedirectResponse
    {
        $data = $this->validatedData($request, $option);
        $data['key'] = $this->normalizeKey($data['group'], $data['key']);
        $data['sort_order'] = $data['sort_order'] ?? 10;
        $data['is_active'] = $request->boolean('is_active');

        $option->update($data);

        return redirect()
            ->route('leads.settings.index', ['tab' => $data['group']])
            ->with('success', 'Lead master option updated successfully.');
    }

    public function destroy(LeadMasterOption $option): RedirectResponse
    {
        $group = $option->group;
        $option->delete();

        return redirect()
            ->route('leads.settings.index', ['tab' => $group])
            ->with('success', 'Lead master option deleted successfully.');
    }

    private function validatedData(Request $request, ?LeadMasterOption $option = null): array
    {
        $id = $option ? $option->id : 'NULL';

        return $request->validate([
            'group' => ['required', Rule::in(array_keys(LeadMasterOption::groupOptions()))],
            'key' => [
                'required',
                'string',
                'max:100',
                'unique:lead_master_options,key,'.$id.',id,group,'.$request->input('group'),
            ],
            'label' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function normalizeKey(string $group, string $key): string
    {
        $key = trim($key);

        if (in_array($group, ['currency', 'incoterm'], true)) {
            return strtoupper(preg_replace('/\s+/', '', $key));
        }

        $key = strtolower($key);
        $key = str_replace([' ', '-'], '_', $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
        $key = preg_replace('/_+/', '_', $key) ?? '';

        return trim($key, '_');
    }
}
