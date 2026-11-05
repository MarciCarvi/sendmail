<?php

namespace App\Services;

use App\Models\DomainCheck;
use App\Models\MailList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Controllo DNS dei domini degli iscritti di una lista (pagina «Controllo domini»). Non esclude né elimina nulla:
 * produce una segnalazione di domini da verificare a mano, con quanti utenti ci sono in lista per ciascuno.
 * Senza librerie: usa dns_get_record() di PHP (se il server lo vieta, l'esito è «controllo non riuscito»).
 */
class DomainCheckService
{
    public const OK      = 'ok';        // ha record MX
    public const NO_MX   = 'no_mx';     // nessun MX, ma il dominio risolve (la posta andrebbe all'indirizzo del sito)
    public const NULL_MX = 'null_mx';   // dichiara di non ricevere posta
    public const DEAD    = 'dead';      // non risolve (dominio inesistente o DNS che non risponde)
    public const UNKNOWN = 'unknown';   // controllo non riuscito (server senza DNS, errore di rete)

    /** Esiti da segnalare, dal più grave. */
    public const PROBLEMS = [self::DEAD, self::NULL_MX, self::NO_MX, self::UNKNOWN];

    /** Dominio sicuramente vivo, usato per capire se è il DNS del server a non funzionare. */
    private const CANARY = 'gmail.com';

    /** [classe badge Bootstrap, etichetta] per esito. */
    public static function label(string $status): array
    {
        return [
            self::OK      => ['success', 'MX ok'],
            self::NO_MX   => ['warning', 'Nessun MX'],
            self::NULL_MX => ['danger', 'Non riceve posta'],
            self::DEAD    => ['danger', 'Non risolve'],
            self::UNKNOWN => ['secondary', 'Controllo non riuscito'],
        ][$status] ?? ['secondary', $status];
    }

    /** Gravità per l'ordinamento: 0 = più grave. */
    public static function severity(string $status): int
    {
        $i = array_search($status, self::PROBLEMS, true);

        return $i === false ? 99 : $i;
    }

    /** Punto unico di accesso al DNS (i test lo sostituiscono). false = errore del resolver. */
    protected function records(string $domain, int $type): array|false
    {
        return @dns_get_record($domain, $type);
    }

    /** Il DNS del server funziona? Se no, i domini risulterebbero tutti «morti»: meglio non controllare. */
    public function dnsAvailable(): bool
    {
        if (!function_exists('dns_get_record')) {
            return false;
        }

        try {
            $mx = $this->records(self::CANARY, DNS_MX);

            return is_array($mx) && !empty($mx);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{status: string, detail: string} */
    public function inspect(string $domain): array
    {
        $domain = strtolower(trim($domain, ". \t\n\r"));
        if ($domain === '' || !function_exists('dns_get_record')) {
            return ['status' => self::UNKNOWN, 'detail' => 'Controllo DNS non disponibile su questo server'];
        }

        // Domini con caratteri non ASCII: il DNS vuole la forma punycode
        if (preg_match('/[^\x20-\x7e]/', $domain) && function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        try {
            // Due tentativi prima di dichiarare un dominio morto: un errore DNS momentaneo non deve bastare
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $mx = $this->records($domain, DNS_MX);
                if ($mx === false) {
                    return ['status' => self::UNKNOWN, 'detail' => 'Il server DNS non ha risposto'];
                }

                if (!empty($mx)) {
                    $targets = array_values(array_filter(array_map(fn($r) => rtrim($r['target'] ?? '', '.'), $mx)));

                    return empty($targets)
                        ? ['status' => self::NULL_MX, 'detail' => 'Il dominio dichiara di non ricevere posta (MX nullo)']
                        : ['status' => self::OK, 'detail' => 'MX: ' . implode(', ', array_slice($targets, 0, 3))];
                }

                $addr = $this->records($domain, DNS_A + DNS_AAAA);
                if ($addr === false) {
                    return ['status' => self::UNKNOWN, 'detail' => 'Il server DNS non ha risposto'];
                }

                if (!empty($addr)) {
                    $ip = $addr[0]['ip'] ?? $addr[0]['ipv6'] ?? '';

                    return ['status' => self::NO_MX, 'detail' => 'Nessun record MX: la posta andrebbe all\'indirizzo del sito' . ($ip ? " ({$ip})" : '')];
                }

                if ($attempt === 1) {
                    usleep(300000);
                }
            }

            return ['status' => self::DEAD, 'detail' => 'Il dominio non risolve (inesistente, scaduto o con DNS non funzionante)'];
        } catch (\Throwable) {
            return ['status' => self::UNKNOWN, 'detail' => 'Controllo non riuscito'];
        }
    }

    /** Salva l'esito del controllo. */
    public function record(string $domain, array $result): DomainCheck
    {
        return DomainCheck::updateOrCreate(['domain' => strtolower($domain)], [
            'status'     => $result['status'],
            'detail'     => mb_substr((string) $result['detail'], 0, 500),
            'checked_at' => now(),
        ]);
    }

    /**
     * Domini degli iscritti della lista con il numero di utenti in lista e di quelli attivi (stato «subscribed»).
     *
     * @return Collection<int, object{domain: string, users: int, active: int}>
     */
    public function listDomains(MailList $list): Collection
    {
        return DB::table('sm_subscribers')
            ->where('list_id', $list->id)
            ->selectRaw("LOWER(TRIM(SUBSTRING_INDEX(email, '@', -1))) as domain, COUNT(*) as users, SUM(status = 'subscribed') as active")
            ->groupBy('domain')
            ->get()
            ->filter(fn($r) => str_contains($r->domain, '.'))
            ->map(fn($r) => (object) ['domain' => $r->domain, 'users' => (int) $r->users, 'active' => (int) $r->active])
            ->values();
    }

    /** Esiti già noti per i domini indicati, per dominio. */
    public function checksFor(array $domains): Collection
    {
        $found = collect();
        foreach (array_chunk($domains, 1000) as $chunk) {
            $found = $found->merge(DomainCheck::whereIn('domain', $chunk)->get());
        }

        return $found->keyBy('domain');
    }
}
