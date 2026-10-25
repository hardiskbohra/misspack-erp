<?php

namespace App\Http\Controllers;

use App\Models\OfficeSetting;
use App\Models\ProjectQualityInspection;
use App\Models\Shipment;
use App\Services\ShipmentDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkflowSettingController extends Controller
{
    private const TABS = ['documents', 'deadlines', 'quality'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'documents';

        return view('settings.workflow', [
            'tab' => $tab,
            'settings' => OfficeSetting::workflow(),
            'documentTypes' => collect(ShipmentDocuments::labels())->except(['other', 'pod'])->all(),
            'shipmentTypes' => [
                Shipment::TYPE_EXPORT => 'Export',
                Shipment::TYPE_IMPORT => 'Import',
                Shipment::TYPE_DOMESTIC => 'Domestic',
            ],
            'qualityChecks' => ProjectQualityInspection::CHECKS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $request->validate(['section' => ['required', Rule::in(self::TABS)]])['section'];
        $settings = OfficeSetting::workflow();

        if ($section === 'documents') {
            $data = $request->validate([
                'documents' => ['nullable', 'array'],
                'documents.*' => ['nullable', 'array'],
                'documents.*.*' => ['string', Rule::in(array_keys(ShipmentDocuments::labels()))],
            ]);
            foreach ([Shipment::TYPE_EXPORT, Shipment::TYPE_IMPORT, Shipment::TYPE_DOMESTIC] as $type) {
                $settings['documents'][$type] = array_values(array_unique((array) data_get($data, 'documents.'.$type, [])));
            }
        } elseif ($section === 'deadlines') {
            $settings['deadlines'] = $request->validate([
                'approval_response_days' => ['required', 'integer', 'min:1', 'max:90'],
                'unassigned_review_days' => ['required', 'integer', 'min:1', 'max:30'],
                'blocked_review_days' => ['required', 'integer', 'min:1', 'max:30'],
                'standard_review_days' => ['required', 'integer', 'min:1', 'max:30'],
            ]);
        } else {
            $data = $request->validate([
                'qc_checks' => ['required', 'array', 'min:1'],
                'qc_checks.*' => ['string', Rule::in(array_keys(ProjectQualityInspection::CHECKS))],
            ]);
            $settings['qc_checks'] = array_values(array_unique($data['qc_checks']));
        }

        OfficeSetting::putWorkflow($settings);

        return redirect()->route('settings.workflow', ['tab' => $section])
            ->with('success', 'Workflow settings saved. New workflow actions use these organisation defaults.');
    }
}
