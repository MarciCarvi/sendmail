<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro dei conteggi di fatturazione: email inviate per mese e per profilo di invio, scritto al momento
        // dell'invio. Gli invii (sm_campaign_sends) si cancellano a cascata con campagne, liste e iscritti: i
        // conteggi degli anni passati devono sopravvivere (AWS ha fatturato quelle email comunque).
        if (!Schema::hasTable('sm_billing_ledger')) {
            Schema::create('sm_billing_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedBigInteger('sender_profile_id')->default(0);   // 0 = campagne senza profilo di invio
                $table->string('profile_name')->nullable();                    // nome del profilo all'ultimo invio (resta se il profilo viene eliminato)
                $table->unsignedBigInteger('emails')->default(0);
                $table->timestamps();

                $table->unique(['year', 'month', 'sender_profile_id'], 'sm_billing_ledger_period_unique');
            });

            $this->backfill();
        }
    }

    /** Riempie il registro dagli invii già presenti (stato «sent», mese in cui sono stati inviati). */
    private function backfill(): void
    {
        $rows = DB::table('sm_campaign_sends as s')
            ->join('sm_campaigns as c', 'c.id', '=', 's.campaign_id')
            ->leftJoin('sm_sender_profiles as p', 'p.id', '=', 'c.sender_profile_id')
            ->where('s.status', 'sent')
            ->whereNotNull('s.sent_at')
            ->selectRaw('YEAR(s.sent_at) as year, MONTH(s.sent_at) as month, COALESCE(c.sender_profile_id, 0) as sender_profile_id, MAX(p.name) as profile_name, COUNT(*) as emails')
            ->groupByRaw('YEAR(s.sent_at), MONTH(s.sent_at), COALESCE(c.sender_profile_id, 0)')
            ->get();

        foreach ($rows->chunk(200) as $chunk) {
            DB::table('sm_billing_ledger')->insertOrIgnore($chunk->map(fn ($r) => [
                'year'              => (int) $r->year,
                'month'             => (int) $r->month,
                'sender_profile_id' => (int) $r->sender_profile_id,
                'profile_name'      => $r->profile_name,
                'emails'            => (int) $r->emails,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_billing_ledger');
    }
};
