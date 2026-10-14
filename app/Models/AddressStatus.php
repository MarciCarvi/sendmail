<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stato di consegna di un indirizzo (bounce permanente o reclamo): uno solo per email, vale per tutte le liste
 * e sopravvive alla cancellazione di liste e iscritti. Le righe di sm_subscribers ne riportano lo stato (elenco
 * ed esclusione dagli invii); questa tabella è la fonte da cui nascono le righe nuove (import, aggiunta singola).
 * Vale per tutti i clienti, come la blacklist: l'account SES e la sua reputazione sono condivisi.
 */
class AddressStatus extends Model
{
    public const BOUNCED = 'bounced';
    public const COMPLAINED = 'complained';

    protected $table = 'sm_address_status';

    protected $fillable = ['email', 'status', 'reason', 'occurred_at'];

    protected $casts = ['occurred_at' => 'datetime'];

    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /** Tutti gli indirizzi segnalati, come [email => status] (per l'import). */
    public static function map(): array
    {
        return static::pluck('status', 'email')
            ->mapWithKeys(fn($status, $email) => [strtolower($email) => $status])
            ->all();
    }

    /**
     * Registra lo stato dell'indirizzo e lo applica alle righe iscritto esistenti (in ogni lista).
     * Il reclamo non viene declassato da un bounce, salvo impostazione manuale ($force: vale l'ultimo stato impostato).
     */
    public static function mark(string $email, string $status, ?string $reason = null, ?Carbon $at = null, bool $force = false): void
    {
        $email = static::normalize($email);
        if ($email === '' || !in_array($status, [self::BOUNCED, self::COMPLAINED], true)) {
            return;
        }

        $current = static::where('email', $email)->first();

        if (!$force && $current?->status === self::COMPLAINED && $status === self::BOUNCED) {
            $status = self::COMPLAINED;
        } else {
            static::updateOrCreate(['email' => $email], [
                'status'      => $status,
                'reason'      => $reason !== null ? mb_substr($reason, 0, 500) : null,
                'occurred_at' => $at ?? now(),
            ]);
        }

        $from = $force
            ? ['subscribed', 'bounced', 'complained']
            : ($status === self::COMPLAINED ? ['subscribed', 'bounced'] : ['subscribed']);

        Subscriber::where('email', $email)
            ->whereIn('status', $from)
            ->where('status', '!=', $status)
            ->update(['status' => $status]);
    }

    /**
     * Riabilita l'indirizzo ovunque: toglie lo stato e rimette «subscribed» le righe bounced/complained.
     * Non tocca i disiscritti né la blacklist. Restituisce quante righe sono tornate attive.
     */
    public static function reinstate(string $email): int
    {
        $email = static::normalize($email);
        if ($email === '') {
            return 0;
        }

        static::where('email', $email)->delete();

        return Subscriber::where('email', $email)
            ->whereIn('status', ['bounced', 'complained'])
            ->update(['status' => 'subscribed']);
    }
}
