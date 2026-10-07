<?php

namespace App\Http\Controllers;

use App\Services\SettingsDirectory;
use Illuminate\View\View;

/**
 * The hub of the settings module — the one page that lists every area.
 *
 * It owns no setting at all: each area's screen and its writes stay with the
 * controller that has always served them, and every area's page renders the same
 * rail (see `resources/views/settings/partials/nav.blade.php`) so a reader can
 * move from one to the next without coming back here.
 *
 * Its job is the two things the rail cannot do: say what is *inside* an area
 * before you open it, and say how much of it there is. Both come from
 * `SettingsDirectory`, so the hub and the rail can never disagree about which
 * areas exist.
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsDirectory $directory)
    {
    }

    public function index(): View
    {
        return view('settings.index', [
            'areas' => $this->directory->areas(),
            'counts' => $this->directory->counts(),
        ]);
    }
}
