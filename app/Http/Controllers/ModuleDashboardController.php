<?php

namespace App\Http\Controllers;

use App\Services\DashboardPeriod;
use App\Services\ModuleDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleDashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.modules', ['modules' => ModuleDashboard::availableModules(request()->user())]);
    }

    public function show(Request $request, string $module, DashboardPeriod $periods, ModuleDashboard $dashboards): View
    {
        abort_unless(array_key_exists($module, ModuleDashboard::MODULES), 404);

        $period = $periods->fromRequest($request);
        $currency = $request->validate(['currency' => ['nullable', 'in:INR,USD,RMB']])['currency'] ?? 'INR';
        $dashboard = $dashboards->build($module, $period, $currency);

        return view('dashboard.module', [
            'module' => $module,
            'modules' => ModuleDashboard::availableModules($request->user()),
            'currency' => $currency,
            'period' => $period,
            'availableYears' => $periods->availableYears(),
            'dashboard' => $dashboard,
        ]);
    }
}
