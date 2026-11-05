<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracciamento anonimo (Garante privacy, provv. n. 284/2026): aperture e click non sono più legati a un
        // iscritto. La colonna subscriber_id resta (le righe già raccolte la usano) ma può essere vuota. Le righe già
        // presenti NON vengono toccate: l'azzeramento dello storico è un pulsante manuale in Impostazioni.
        foreach (['sm_campaign_opens', 'sm_campaign_clicks'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('subscriber_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Irreversibile di proposito: le righe anonime non hanno un iscritto a cui tornare.
    }
};
