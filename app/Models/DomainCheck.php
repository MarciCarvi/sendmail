<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Esito dell'ultimo controllo DNS/MX di un dominio (vedi DomainCheckService). */
class DomainCheck extends Model
{
    protected $table = 'sm_domain_checks';

    protected $fillable = ['domain', 'status', 'detail', 'checked_at'];

    protected $casts = ['checked_at' => 'datetime'];
}
