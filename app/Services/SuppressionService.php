<?php

namespace App\Services;

use App\Models\AddressStatus;
use App\Models\Setting;
use App\Models\SesSuppression;
use Aws\Exception\AwsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sincronizza la suppression list di Amazon SES (per account e regione, condivisa da tutti i clienti) con una copia
 * locale e con sm_address_status, così gli indirizzi bloccati da SES vengono esclusi PRIMA dell'invio (e non
 * compaiono nel rapporto del cliente come non consegnati).
 *
 * - Sincronizzazione completa: a pagine, guidata dal browser (come il controllo MX). Alla fine toglie dalla copia
 *   le voci che SES non ha più.
 * - Incrementale: solo le voci aggiornate dall'ultima sincronizzazione; parte da sola prima di ogni invio e ogni
 *   giorno, ma solo dopo che la prima sincronizzazione completa è stata fatta a mano.
 * - Una riabilitazione manuale («Riabilita ovunque») non viene annullata: un indirizzo viene segnato solo quando la
 *   voce di SES è nuova o è stata aggiornata.
 */
class SuppressionService
{
    public const SETTING_SYNCED_AT = 'ses_suppression_synced_at';
    public const SETTING_ERROR     = 'ses_suppression_sync_error';

    public function __construct(private SesService $ses)
    {
    }

    public function lastSync(): ?Carbon
    {
        $value = Setting::get(self::SETTING_SYNCED_AT);

        return $value ? Carbon::parse($value) : null;
    }

    public function lastError(): ?string
    {
        return Setting::get(self::SETTING_ERROR) ?: null;
    }

    /**
     * Elabora una pagina della lista di SES. $pass identifica la passata (data/ora d'inizio); $since limita alle voci
     * aggiornate da quella data (incrementale).
     *
     * @return array{processed: int, marked: int, next: ?string}
     */
    public function syncPage(?string $token, Carbon $pass, ?Carbon $since = null): array
    {
        $page = $this->ses->listSuppressedDestinations($token, $since);

        $entries = collect($page['entries'])
            ->map(fn($e) => ['email' => AddressStatus::normalize($e['email'])] + $e)
            ->filter(fn($e) => $e['email'] !== '')
            ->unique('email')
            ->values();

        $emails   = $entries->pluck('email')->all();
        $existing = SesSuppression::whereIn('email', $emails)->get()->keyBy('email');
        $statuses = AddressStatus::whereIn('email', $emails)->pluck('status', 'email')->all();

        $marked = 0;
        foreach ($entries as $e) {
            $row     = $existing->get($e['email']);
            $updated = $e['updated'];

            $changed = !$row
                || $row->reason !== $e['reason']
                || ($row->listed_at?->timestamp) !== ($updated?->timestamp);

            SesSuppression::updateOrCreate(['email' => $e['email']], [
                'reason'    => $e['reason'],
                'listed_at' => $updated,
                'synced_at' => $pass,
            ]);

            if ($changed && $this->applyToAddress($e['email'], $e['reason'], $updated, $statuses[$e['email']] ?? null)) {
                $marked++;
            }
        }

        return ['processed' => $entries->count(), 'marked' => $marked, 'next' => $page['next']];
    }

    /** Chiude una sincronizzazione completa: toglie dalla copia le voci che SES non ha più. */
    public function finishFull(Carbon $pass): int
    {
        $removed = SesSuppression::where(fn($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', $pass))->delete();

        Setting::set(self::SETTING_SYNCED_AT, $pass->toIso8601String());
        Setting::set(self::SETTING_ERROR, '');

        return $removed;
    }

    /**
     * Aggiornamento automatico (prima di un invio, ogni giorno): solo le voci nuove. Non lancia mai eccezioni:
     * un errore (permesso IAM mancante, rete) viene registrato e non blocca l'invio.
     * Restituisce le voci elaborate, o null se non è stato eseguito.
     */
    public function syncIncremental(int $maxPages = 5): ?int
    {
        $last = $this->lastSync();
        if (!$last) {
            return null; // la prima sincronizzazione completa si fa a mano dalla pagina
        }

        try {
            $pass  = now();
            $since = $last->copy()->subDay();
            $token = null;
            $count = 0;

            for ($i = 0; $i < $maxPages; $i++) {
                $r = $this->syncPage($token, $pass, $since);
                $count += $r['processed'];
                $token = $r['next'];
                if (!$token) {
                    break;
                }
            }

            if (!$token) {
                Setting::set(self::SETTING_SYNCED_AT, $pass->toIso8601String());
                Setting::set(self::SETTING_ERROR, '');
            }

            return $count;
        } catch (\Throwable $e) {
            Setting::set(self::SETTING_ERROR, self::friendlyError($e));
            Log::warning('Sincronizzazione suppression list SES fallita', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Segna l'indirizzo come bounced/complained se non lo è già (non sovrascrive un motivo più dettagliato). */
    private function applyToAddress(string $email, string $reason, ?Carbon $at, ?string $current): bool
    {
        $status = $reason === SesSuppression::COMPLAINT ? AddressStatus::COMPLAINED : AddressStatus::BOUNCED;

        if ($reason !== SesSuppression::BOUNCE && $reason !== SesSuppression::COMPLAINT) {
            return false;
        }

        $upgrade = $current === AddressStatus::BOUNCED && $status === AddressStatus::COMPLAINED;
        if ($current !== null && !$upgrade) {
            return false;
        }

        AddressStatus::mark($email, $status, "Suppression list SES ({$reason})", $at);

        return true;
    }

    /** Messaggio comprensibile per gli errori più comuni. */
    public static function friendlyError(\Throwable $e): string
    {
        if ($e instanceof AwsException) {
            $code = strtolower((string) $e->getAwsErrorCode());

            if (str_contains($code, 'accessdenied') || str_contains($code, 'unauthorized')) {
                return 'Permesso mancante: aggiungi l\'azione ses:ListSuppressedDestinations alla policy IAM dell\'utente usato da SendMail (Impostazioni).';
            }
            if (str_contains($code, 'invalidclienttokenid') || str_contains($code, 'signaturedoesnotmatch') || str_contains($code, 'unrecognizedclient')) {
                return 'Credenziali SES non valide: controllale in Impostazioni.';
            }
            if (!$e->getAwsErrorCode()) {
                return 'SES non raggiungibile: ' . mb_substr($e->getMessage(), 0, 200);
            }

            return 'Errore SES (' . $e->getAwsErrorCode() . '): ' . mb_substr((string) $e->getAwsErrorMessage(), 0, 200);
        }

        return 'Errore: ' . mb_substr($e->getMessage(), 0, 200);
    }
}
