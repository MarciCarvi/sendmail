<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private const CACHE_KEY   = 'license_check_result';
    private const CACHE_HOURS = 6;

    public function check(): array
    {
        return cache()->remember(self::CACHE_KEY, now()->addHours(self::CACHE_HOURS), fn () => $this->performCheck());
    }

    public function invalidateCache(): void
    {
        cache()->forget(self::CACHE_KEY);
    }

    public function getCurrentVersion(): string
    {
        return config('sendmail.version', '1.0.0');
    }

    private function performCheck(): array
    {
        $licenseKey = Setting::get('license_key');

        if (!$licenseKey) {
            return $this->applyGrace('Nessuna licenza configurata. Inserisci la tua chiave nelle impostazioni.');
        }

        $result = $this->validateWithGitHub($licenseKey);

        if ($result['valid']) {
            Setting::set('license_grace_start', null);
            return ['valid' => true, 'grace' => false, 'days_left' => null, 'error' => null, 'status' => 'valid'];
        }

        return $this->applyGrace($result['error'] ?? 'Licenza non valida.');
    }

    private function applyGrace(string $error): array
    {
        $graceStart = Setting::get('license_grace_start');

        if (!$graceStart) {
            Setting::set('license_grace_start', now()->toIso8601String());
            $graceStart = now()->toIso8601String();
        }

        $daysElapsed = (int) now()->diffInDays(Carbon::parse($graceStart), true);
        $graceDays   = (int) config('sendmail.license.grace_days', 3);
        $daysLeft    = max(0, $graceDays - $daysElapsed);

        if ($daysLeft > 0) {
            return ['valid' => false, 'grace' => true, 'days_left' => $daysLeft, 'error' => $error, 'status' => 'grace'];
        }

        return ['valid' => false, 'grace' => false, 'days_left' => 0, 'error' => $error, 'status' => 'blocked'];
    }

    private function validateWithGitHub(string $licenseKey): array
    {
        $pat  = config('sendmail.license.pat');
        $repo = config('sendmail.license.repo');
        $file = config('sendmail.license.file');

        if (!$pat || !$repo) {
            return ['valid' => false, 'error' => 'Server licenze non configurato (SM_LICENSE_PAT mancante).'];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $pat,
                    'Accept'        => 'application/vnd.github.v3+json',
                    'User-Agent'    => 'SendMail-License/1.0',
                ])
                ->get("https://api.github.com/repos/{$repo}/contents/{$file}");

            if ($response->status() === 404) {
                return ['valid' => false, 'error' => 'File licenze non trovato nel repository.'];
            }

            if (!$response->successful()) {
                return ['valid' => false, 'error' => 'Server licenze non raggiungibile (HTTP ' . $response->status() . ').'];
            }

            $raw      = $response->json('content');
            $licenses = json_decode(base64_decode(str_replace(["\n", "\r"], '', $raw)), true) ?? [];

            if (!array_key_exists($licenseKey, $licenses)) {
                return ['valid' => false, 'error' => 'Codice licenza non riconosciuto.'];
            }

            $entry  = $licenses[$licenseKey];
            $domain = $this->getCurrentDomain();

            if (empty($entry['domain'])) {
                $sha = $response->json('sha');
                $licenses[$licenseKey]['domain']   = $domain;
                $licenses[$licenseKey]['bound_at'] = now()->toIso8601String();
                $this->updateGitHubFile($licenses, $sha);
                return ['valid' => true];
            }

            if ($entry['domain'] !== $domain) {
                return ['valid' => false, 'error' => "Licenza già associata al dominio '{$entry['domain']}'. Contatta il supporto per trasferirla."];
            }

            return ['valid' => true];

        } catch (\Throwable $e) {
            Log::warning('SendMail license check failed: ' . $e->getMessage());
            return ['valid' => false, 'error' => 'Impossibile contattare il server licenze. Riprova più tardi.'];
        }
    }

    private function updateGitHubFile(array $licenses, string $sha): void
    {
        $pat  = config('sendmail.license.pat');
        $repo = config('sendmail.license.repo');
        $file = config('sendmail.license.file');

        try {
            Http::timeout(8)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $pat,
                    'Accept'        => 'application/vnd.github.v3+json',
                    'User-Agent'    => 'SendMail-License/1.0',
                ])
                ->put("https://api.github.com/repos/{$repo}/contents/{$file}", [
                    'message' => 'Bind license to ' . $this->getCurrentDomain(),
                    'content' => base64_encode(json_encode($licenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                    'sha'     => $sha,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SendMail license bind failed: ' . $e->getMessage());
        }
    }

    private function getCurrentDomain(): string
    {
        $url  = config('app.url', '');
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return strtolower(trim($host));
    }
}
