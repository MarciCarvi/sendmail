<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\LicenseService;
use App\Services\SesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index(LicenseService $license)
    {
        $settings = [
            'app_name'           => Setting::get('app_name', config('app.name')),
            'default_from_name'  => Setting::get('default_from_name'),
            'default_from_email' => Setting::get('default_from_email'),
            'ses_key'            => Setting::getEncrypted('ses_key'),
            'ses_secret'         => Setting::getEncrypted('ses_secret'),
            'ses_region'         => Setting::get('ses_region', 'eu-west-1'),
            'ses_sending_rate'        => Setting::get('ses_sending_rate', '14'),
            'ses_configuration_set'   => Setting::get('ses_configuration_set', ''),
            'blocked_domains'    => Setting::get('blocked_domains', ''),
            'report_brand_name'  => Setting::get('report_brand_name', ''),
            'report_logo'        => Setting::get('report_logo'),
            'license_key'        => Setting::get('license_key', ''),
        ];

        $licenseStatus = $license->check();

        return view('settings.index', compact('settings', 'licenseStatus'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_name'           => 'required|string|max:100',
            'default_from_name'  => 'required|string|max:100',
            'default_from_email' => 'required|email',
            'ses_key'            => 'nullable|string',
            'ses_secret'         => 'nullable|string',
            'ses_region'         => 'required|string',
            'ses_sending_rate'        => 'required|integer|min:1|max:200',
            'ses_configuration_set'   => 'nullable|string|max:100',
            'blocked_domains'    => 'nullable|string',
            'license_key'        => 'nullable|string|max:64',
        ]);

        Setting::set('app_name', $request->app_name);
        Setting::set('default_from_name', $request->default_from_name);
        Setting::set('default_from_email', $request->default_from_email);
        Setting::set('ses_region', $request->ses_region);
        Setting::set('ses_sending_rate', $request->ses_sending_rate);
        Setting::set('ses_configuration_set', $request->ses_configuration_set ?? '');
        Setting::set('blocked_domains', $request->blocked_domains ?? '');

        if ($request->filled('ses_key')) {
            Setting::setEncrypted('ses_key', $request->ses_key);
        }
        if ($request->filled('ses_secret')) {
            Setting::setEncrypted('ses_secret', $request->ses_secret);
        }

        $oldKey = Setting::get('license_key');
        if ($request->license_key !== null) {
            Setting::set('license_key', trim($request->license_key));
            if (trim($request->license_key) !== $oldKey) {
                app(\App\Services\LicenseService::class)->invalidateCache();
                Setting::set('license_grace_start', null);
            }
        }

        return back()->with('success', 'Impostazioni salvate.');
    }

    /** Nome e logo mostrati nei rapporti per il cliente. */
    public function updateReportBrand(Request $request)
    {
        $request->validate([
            'report_brand_name' => 'nullable|string|max:100',
            'report_logo'       => 'nullable|file|image|mimes:png,jpg,jpeg,webp|max:1024',
        ]);

        Setting::set('report_brand_name', trim((string) $request->report_brand_name));

        if ($request->boolean('remove_logo') || $request->hasFile('report_logo')) {
            if ($old = Setting::get('report_logo')) {
                Storage::disk('public')->delete($old);
            }
            Setting::set('report_logo', null);
        }

        if ($request->hasFile('report_logo')) {
            $file = $request->file('report_logo');
            $path = $file->storeAs('branding', 'report-logo-' . time() . '.' . strtolower($file->getClientOriginalExtension()), 'public');
            Setting::set('report_logo', $path);
        }

        return back()->with('success', 'Marchio dei rapporti salvato.');
    }

    public function testSes()
    {
        try {
            $ses = app(SesService::class);
            $quota = $ses->getSendingQuota();
            return response()->json([
                'success' => true,
                'message' => "Connessione OK — Quota: {$quota['SentLast24Hours']}/{$quota['Max24HourSend']} email/24h — Rate massimo: {$quota['MaxSendRate']} email/sec",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
