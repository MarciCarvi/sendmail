<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disiscritti per "cliente": la chiave è il dominio dell'email mittente della lista
 * (il from_email della lista coincide con quello del profilo di invio).
 * Chi si disiscrive da una lista non riceve più campagne con un mittente di quel dominio.
 */
class Unsubscribe extends Model
{
    protected $table = 'sm_unsubscribes';

    protected $fillable = ['email', 'sender_domain', 'sender_email', 'list_id', 'unsubscribed_at'];

    protected $casts = ['unsubscribed_at' => 'datetime'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'list_id');
    }

    public static function domainOf(?string $email): string
    {
        $at = strrpos((string) $email, '@');
        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    /** Registra la disiscrizione dell'iscritto per il cliente (dominio) della sua lista. */
    public static function record(Subscriber $subscriber): void
    {
        $list = $subscriber->list;
        $domain = static::domainOf($list?->from_email);
        if (!$list || $domain === '') {
            return;
        }

        static::updateOrCreate(
            ['sender_domain' => $domain, 'email' => strtolower($subscriber->email)],
            [
                'sender_email'    => strtolower($list->from_email),
                'list_id'         => $list->id,
                'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
            ]
        );
    }

    /** Toglie l'iscritto dai disiscritti del cliente della sua lista (nuova iscrizione volontaria). */
    public static function clear(Subscriber $subscriber): void
    {
        $domain = static::domainOf($subscriber->list?->from_email);
        if ($domain === '') {
            return;
        }

        static::where('sender_domain', $domain)
            ->where('email', strtolower($subscriber->email))
            ->delete();
    }

    /** Email disiscritte per il dominio di questo mittente, come array [email => true]. */
    public static function suppressedFor(?string $senderEmail): array
    {
        $domain = static::domainOf($senderEmail);
        if ($domain === '') {
            return [];
        }

        return static::where('sender_domain', $domain)->pluck('email')
            ->mapWithKeys(fn($e) => [strtolower($e) => true])
            ->all();
    }
}
