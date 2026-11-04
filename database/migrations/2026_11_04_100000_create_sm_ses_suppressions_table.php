<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copia locale della suppression list di Amazon SES (per account e regione): la pagina «Suppression list»
        // la mostra divisa per cliente. È solo una copia: la fonte resta SES; l'esclusione dagli invii passa da
        // sm_address_status.
        if (!Schema::hasTable('sm_ses_suppressions')) {
            Schema::create('sm_ses_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email');                       // minuscola
                $table->string('reason', 20);                  // BOUNCE | COMPLAINT
                $table->timestamp('listed_at')->nullable();    // LastUpdateTime di SES
                $table->timestamp('synced_at')->nullable();    // ultima sincronizzazione completa in cui era presente
                $table->timestamps();

                $table->unique('email', 'sm_ses_suppressions_email_unique');
                $table->index('reason', 'sm_ses_suppressions_reason_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_ses_suppressions');
    }
};
