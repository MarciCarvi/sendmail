<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Indirizzi segnalati come "non consegnati": N invii consecutivi senza consegna (o con bounce).
 * Sono esclusi dagli invii finché non vengono riabilitati (cleared_at valorizzato).
 */
class Undelivered extends Model
{
    protected $table = 'sm_undelivered';

    protected $fillable = [
        'email', 'domain', 'consecutive', 'last_campaign_id', 'evidence',
        'mx_status', 'mx_checked_at', 'suggestion', 'flagged_at', 'cleared_at',
    ];

    protected $casts = [
        'evidence'      => 'array',
        'mx_checked_at' => 'datetime',
        'flagged_at'    => 'datetime',
        'cleared_at'    => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('cleared_at');
    }

    /** Email attualmente segnalate, come array [email => true]. */
    public static function activeEmails(): array
    {
        return static::active()->pluck('email')
            ->mapWithKeys(fn($e) => [strtolower($e) => true])
            ->all();
    }
}
