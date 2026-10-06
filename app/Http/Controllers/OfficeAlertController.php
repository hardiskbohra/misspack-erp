<?php

namespace App\Http\Controllers;

use App\Models\OfficeAlert;
use App\Services\OfficeBriefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeAlertController extends Controller
{
    public function inbox(Request $request, OfficeBriefing $briefing): JsonResponse
    {
        return response()->json($briefing->payload($request->user()));
    }

    public function seen(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse
    {
        $briefing->markSeen($office_alert, $request->user());

        return response()->json($briefing->payload($request->user()));
    }

    public function ack(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse
    {
        $briefing->markAcked($office_alert, $request->user());

        return response()->json($briefing->payload($request->user()));
    }

    public function snooze(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse
    {
        $until = $request->validate([
            'until' => ['required', 'in:1h,4h,tomorrow'],
        ])['until'];

        $briefing->snooze($office_alert, $request->user(), $until);

        return response()->json($briefing->payload($request->user()));
    }

    public function popupShown(Request $request, OfficeAlert $office_alert, OfficeBriefing $briefing): JsonResponse
    {
        $briefing->markPopupShown($office_alert, $request->user());

        return response()->json(['ok' => true]);
    }
}
