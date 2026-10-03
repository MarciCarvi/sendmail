<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Indirizzi non consegnati per N invii consecutivi (globale, come la blacklist)
        Schema::create('sm_undelivered', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('domain', 191)->index();
            $table->unsignedSmallInteger('consecutive')->default(3);   // quanti invii consecutivi hanno fatto scattare la segnalazione
            $table->unsignedBigInteger('last_campaign_id')->nullable();
            $table->json('evidence')->nullable();                      // gli ultimi invii: campagna, data, motivo
            $table->string('mx_status', 20)->nullable();               // ok / no_mx / null_mx / dead / unknown
            $table->timestamp('mx_checked_at')->nullable();
            $table->string('suggestion', 191)->nullable();             // dominio corretto in caso di refuso evidente
            $table->timestamp('flagged_at')->nullable();
            $table->timestamp('cleared_at')->nullable();               // riabilitato: conta solo quanto accade dopo
            $table->timestamps();
        });

        // Diagnostica del registro eventi: da dove arriva ogni evento e quanti doppioni sono stati scartati
        Schema::table('sm_ses_events', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('event_type');          // eventType | notificationType
            $table->string('topic_arn')->nullable()->after('source');
            $table->unsignedSmallInteger('duplicates')->default(0)->after('topic_arn');
            $table->string('dup_source', 120)->nullable()->after('duplicates');
        });
    }

    public function down(): void
    {
        Schema::table('sm_ses_events', function (Blueprint $table) {
            $table->dropColumn(['source', 'topic_arn', 'duplicates', 'dup_source']);
        });
        Schema::dropIfExists('sm_undelivered');
    }
};
