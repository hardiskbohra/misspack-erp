<?php

namespace App\Http\Controllers;

use App\Models\OfficeAlert;
use App\Services\OfficeBriefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeAlertController extends Controller
{
    /**
     * The notification centre: the bell's whole desk as a page — open,
     * snoozed and resolved briefings with who-cleared-what on the record.
     * The dropdown answers "what needs me now"; this answers "what happened
     * while I was on the floor".
     */
    public function centre(Request $request, OfficeBriefing $briefing): View
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $query = $request->validate([
            'team' => ['nullable', Rule::in(['all', 'office', 'sales', 'operations', 'accounts'])],
            'severity' => ['nullable', Rule::in(['all', 'critical', 'attention', 'info'])],
            'state' => ['nullable', Rule::in(['open', 'snoozed', 'resolved'])],
        ]);

        $filters = array_merge(['team' => 'all', 'severity' => 'all', 'state' => 'open'], $query ?: []);

        return view('office_alerts.centre', $briefing->centre($user, $filters) + [
            'filters' => $filters,
        ]);
    }

    public function inbox(Request $request, OfficeBriefing $briefing): JsonResponse
    {
        return response()->json($briefing->payload($request->user()));
    }

    public function seen(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse|RedirectResponse
    {
        $briefing->markSeen($office_alert, $request->user());

        return $request->expectsJson()
            ? response()->json($briefing->payload($request->user()))
            : back()->with('status', 'Marked as seen — it stays in the centre\'s history.');
    }

    public function ack(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse|RedirectResponse
    {
        $briefing->markAcked($office_alert, $request->user());

        return $request->expectsJson()
            ? response()->json($briefing->payload($request->user()))
            : back()->with('status', 'Marked as read — it stays in the centre\'s history.');
    }

    public function snooze(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse|RedirectResponse
    {
        $until = $request->validate([
            'until' => ['required', 'in:1h,4h,tomorrow'],
        ])['until'];

        $briefing->snooze($office_alert, $request->user(), $until);

        return $request->expectsJson()
            ? response()->json($briefing->payload($request->user()))
            : back()->with('status', 'Snoozed — it sits in the centre\'s Snoozed tray until it wakes.');
    }

    public function popupShown(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse
    {
        $briefing->markPopupShown($office_alert, $request->user());

        return response()->json(['ok' => true]);
    }
}
