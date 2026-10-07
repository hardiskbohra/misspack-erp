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
        $watcherIds = $settings['watcher_ids'] ?? null;

        $watchers = User::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->isAdmin())
            ->map(function (User $user) use ($watcherIds) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'watching' => ! is_array($watcherIds) || in_array((int) $user->id, array_map('intval', $watcherIds), true),
                    'desk' => OfficeSetting::deskFor($user),
                ];
            })
            ->values();

        return view('settings.briefings', [
            'settings' => $settings,
            'sources' => OfficeSetting::sourceLabels(),
            'desks' => OfficeSetting::deskOptions(),
            'watchers' => $watchers,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chase_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'stale_days' => ['required', 'integer', 'min:1', 'max:14'],
            'extra_emails' => ['nullable', 'string', 'max:4000'],
            'sources' => ['nullable', 'array'],
            'watchers' => ['nullable', 'array'],
            'watchers.*.on' => ['nullable'],
            'watchers.*.desk' => ['nullable', 'in:office,sales,operations,accounts'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['extra_emails'] ?? '')))
            ->filter()
            ->unique()
            ->implode("\n");

        $sources = [];
        foreach (array_keys(OfficeSetting::sourceLabels()) as $key) {
            $sources[$key] = $request->boolean('sources.'.$key);
        }

        $adminIds = User::query()->get()->filter(fn (User $user) => $user->isAdmin())->pluck('id')->all();
        $posted = $data['watchers'] ?? [];
        $watcherIds = [];
        $desks = [];

        foreach ($adminIds as $id) {
            $row = $posted[$id] ?? $posted[(string) $id] ?? [];
            $desk = $row['desk'] ?? 'office';
            if (! array_key_exists($desk, OfficeSetting::deskOptions())) {
                $desk = 'office';
            }
            $desks[$id] = $desk;
            if (! empty($row['on'])) {
                $watcherIds[] = (int) $id;
            }
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
            'watcher_ids' => $watcherIds,
            'watcher_desks' => $desks,
        ]);

        return redirect()
            ->route('settings.briefings')
            ->with('success', 'Office briefing settings saved.');
    }
}
