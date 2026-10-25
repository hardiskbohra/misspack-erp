<?php

namespace App\Http\Controllers;

use App\Models\OfficeSetting;
use App\Services\PackagingLabelQr;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackagingListSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.packaging', ['settings' => OfficeSetting::packaging()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label_qr_url' => ['bail', 'nullable', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail): void {
                if (! PackagingLabelQr::validUrl($value)) {
                    $fail('Use a full http:// or https:// URL, without a username or password, up to 200 bytes long.');
                }
            }],
        ]);
        OfficeSetting::putPackaging(['label_qr_url' => $data['label_qr_url'] ?? '']);

        return redirect()->route('settings.packaging')
            ->with('success', 'Packaging settings saved. Newly generated labels use this QR link.');
    }
}
