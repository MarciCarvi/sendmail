<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Allow settings and update routes so the user can fix a license issue
        if ($request->routeIs('settings.*', 'update.*', 'license.*')) {
            return $next($request);
        }

        $status = $this->license->check();

        if ($status['status'] === 'blocked') {
            return response()->view('license.blocked', ['status' => $status], 403);
        }

        return $next($request);
    }
}
