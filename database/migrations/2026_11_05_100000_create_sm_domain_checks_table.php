<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esito dell'ultimo controllo DNS/MX di un dominio (pagina «Controllo domini» di una lista). Un dominio, una riga:
        // vale per tutte le liste.
        if (!Schema::hasTable('sm_domain_checks')) {
            Schema::create('sm_domain_checks', function (Blueprint $table) {
                $table->id();
                $table->string('domain');                       // minuscolo
                $table->string('status', 20);                   // ok | no_mx | null_mx | dead | unknown
                $table->string('detail', 500)->nullable();      // es. «MX: mail.esempio.it»
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->unique('domain', 'sm_domain_checks_domain_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_domain_checks');
    }
};
