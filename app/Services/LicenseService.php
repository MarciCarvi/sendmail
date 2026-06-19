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

        $result = $this->validateWithServer($licenseKey);

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

    /**
     * Valida la chiave contro il license server. Il server firma la risposta con
     * RSA; verifichiamo la firma con la chiave pubblica embedded nella config.
     * In questo modo nessun segreto vive sul client e una risposta non firmata
     * dal nostro server (host spoofato, MITM) viene rifiutata.
     */
    private function validateWithServer(string $licenseKey): array
    {
        $endpoint  = config('sendmail.license.api');
        $publicKey = config('sendmail.license.public_key');

        if (!$endpoint || !$publicKey) {
            return ['valid' => false, 'error' => 'Server licenze non configurato.'];
        }

        $domain = $this->getCurrentDomain();

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'key'     => $licenseKey,
                    'domain'  => $domain,
                    'version' => $this->getCurrentVersion(),
                ]);

            if (!$response->successful()) {
                return ['valid' => false, 'error' => 'Server licenze non raggiungibile (HTTP ' . $response->status() . ').'];
            }

            $payload   = (string) $response->json('payload', '');
            $signature = base64_decode((string) $response->json('signature', ''), true);

            if ($payload === '' || $signature === false) {
                return ['valid' => false, 'error' => 'Risposta licenza malformata.'];
            }

            // Verifica firma RSA-SHA256.
            if (openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
                return ['valid' => false, 'error' => 'Firma licenza non valida.'];
            }

            $data = json_decode($payload, true);
            if (!is_array($data)) {
                return ['valid' => false, 'error' => 'Payload licenza non valido.'];
            }

            // Lega la risposta a QUESTO dominio e controlla la scadenza: una
            // risposta valida di un'altra installazione non è riutilizzabile qui.
            if (($data['domain'] ?? null) !== $domain) {
                return ['valid' => false, 'error' => 'Licenza associata a un altro dominio.'];
            }
            if ((int) ($data['expires'] ?? 0) < time()) {
                return ['valid' => false, 'error' => 'Risposta licenza scaduta.'];
            }

            if (!empty($data['valid'])) {
                return ['valid' => true];
            }

            $message = match ($data['status'] ?? '') {
                'unknown'         => 'Codice licenza non riconosciuto.',
                'suspended'       => 'Licenza sospesa. Contatta il supporto.',
                'domain_mismatch' => 'Licenza già associata a un altro dominio.',
                default           => 'Licenza non valida.',
            };

            return ['valid' => false, 'error' => $message];

        } catch (\Throwable $e) {
            Log::warning('SendMail license check failed: ' . $e->getMessage());
            return ['valid' => false, 'error' => 'Impossibile contattare il server licenze. Riprova più tardi.'];
        }
    }

    private function getCurrentDomain(): string
    {
        $url  = config('app.url', '');
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return strtolower(trim($host));
    }
}
