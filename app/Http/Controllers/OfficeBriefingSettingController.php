<?php

namespace App\Http\Controllers;

use App\Models\OfficeSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficeBriefingSettingController extends Controller
{
    public function index(): View
    {
        $settings = OfficeSetting::briefings();

        $watchers = User::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->isAdmin())
            ->map(function (User $user) {
                $desk = trim((string) $user->department);
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'desk' => $desk === '' ? 'All desks' : $desk,
                ];
            })
            ->values();

        return view('office_briefings.settings', [
            'settings' => $settings,
            'sources' => OfficeSetting::sourceLabels(),
            'watchers' => $watchers,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chase_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'stale_days' => ['required', 'integer', 'min:1', 'max:14'],
            'extra_emails' => ['nullable', 'string', 'max:2000'],
            'sources' => ['nullable', 'array'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['extra_emails'] ?? '')))
            ->filter()
            ->unique()
            ->implode(', ');

        $sources = [];
        foreach (array_keys(OfficeSetting::sourceLabels()) as $key) {
            $sources[$key] = $request->boolean('sources.'.$key);
        }

        OfficeSetting::putBriefings([
            'enabled' => $request->boolean('enabled'),
            'popups' => $request->boolean('popups'),
            'emails' => $request->boolean('emails'),
            'chase_emails' => $request->boolean('chase_emails'),
            'chase_hours' => (int) $data['chase_hours'],
            'stale_days' => (int) $data['stale_days'],
            'extra_emails' => $emails,
            'sources' => $sources,
        ]);

        return redirect()
            ->route('office-alerts.settings')
            ->with('success', 'Office briefing settings saved.');
    }
}
