<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use App\Services\UpdateService;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function check(UpdateService $updates)
    {
        return response()->json($updates->checkForUpdates());
    }

    public function forceCheck(UpdateService $updates)
    {
        return response()->json($updates->checkForUpdates(forceRefresh: true));
    }

    public function apply(Request $request, UpdateService $updates)
    {
        $request->validate(['version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/']]);

        @set_time_limit(300);
        $result = $updates->applyUpdate($request->version);

        if ($result['success']) {
            session()->flash('show_changelog', true);
            session()->flash('updated_version', $request->version);
        }

        return response()->json($result);
    }

    public function changelog()
    {
        $path = base_path('CHANGELOG.md');
        return response()->json([
            'content' => file_exists($path) ? file_get_contents($path) : '',
        ]);
    }

    public function licenseCheck(LicenseService $license)
    {
        $license->invalidateCache();
        return response()->json($license->check());
    }
}
