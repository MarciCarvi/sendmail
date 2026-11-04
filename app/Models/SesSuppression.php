<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Copia locale della suppression list di Amazon SES. SES blocca da sola gli invii a questi indirizzi (per account
 * e regione, quindi anche quelli generati da altri clienti): qui servono solo per mostrarli per cliente e per
 * segnarli in sm_address_status prima dell'invio.
 */
class SesSuppression extends Model
{
    public const BOUNCE = 'BOUNCE';
    public const COMPLAINT = 'COMPLAINT';

    protected $table = 'sm_ses_suppressions';

    protected $fillable = ['email', 'reason', 'listed_at', 'synced_at'];

    protected $casts = ['listed_at' => 'datetime', 'synced_at' => 'datetime'];
}
