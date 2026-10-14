<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stato di consegna per indirizzo (bounce permanente o reclamo): uno solo per email, vale per tutte le liste
        // e sopravvive alla cancellazione di liste e iscritti.
        if (!Schema::hasTable('sm_address_status')) {
            Schema::create('sm_address_status', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('status', 20);                          // bounced / complained
                $table->string('reason', 500)->nullable();             // es. «Bounce Permanent / General: smtp; 554 …»
                $table->timestamp('occurred_at')->nullable();          // data dell'evento (o dell'impostazione manuale)
                $table->timestamps();
            });
        }

        $this->backfill();
    }

    /** Costruisce la tabella dagli stati già presenti sulle righe iscritto (reclamo prevale su bounce). */
    private function backfill(): void
    {
        $emails = DB::table('sm_subscribers')
            ->whereIn('status', ['bounced', 'complained'])
            ->selectRaw("email, MAX(status = 'complained') AS has_complaint, MAX(updated_at) AS last_update")
            ->groupBy('email')
            ->get();

        foreach ($emails->chunk(200) as $chunk) {
            $rows = [];

            foreach ($chunk as $entry) {
                $email = strtolower(trim($entry->email));
                $complained = (bool) $entry->has_complaint;

                // data e motivo dall'ultimo esito registrato sugli invii di quell'indirizzo, se c'è
                $send = DB::table('sm_campaign_sends as cs')
                    ->join('sm_subscribers as s', 's.id', '=', 'cs.subscriber_id')
                    ->where('s.email', $entry->email)
                    ->when(
                        $complained,
                        fn($q) => $q->whereNotNull('cs.complained_at'),
                        fn($q) => $q->where('cs.bounce_type', 'Permanent')->whereNotNull('cs.bounced_at')
                    )
                    ->orderByDesc($complained ? 'cs.complained_at' : 'cs.bounced_at')
                    ->first(['cs.message_id', 'cs.bounced_at', 'cs.bounce_type', 'cs.bounce_subtype', 'cs.complained_at']);

                $reason = 'Stato presente prima dell\'aggiornamento';
                $at = $entry->last_update;

                if ($send && $complained) {
                    $reason = 'Segnalata come spam';
                    $at = $send->complained_at;
                } elseif ($send) {
                    $diagnostic = $send->message_id
                        ? DB::table('sm_ses_events')->where('message_id', $send->message_id)
                            ->where('event_type', 'Bounce')->orderByDesc('id')->value('diagnostic')
                        : null;
                    $type = trim(($send->bounce_type ?? '') . ' / ' . ($send->bounce_subtype ?? ''), ' /');
                    $reason = 'Bounce ' . $type . ($diagnostic ? ': ' . $diagnostic : '');
                    $at = $send->bounced_at;
                }

                $rows[] = [
                    'email'       => $email,
                    'status'      => $complained ? 'complained' : 'bounced',
                    'reason'      => mb_substr($reason, 0, 500),
                    'occurred_at' => $at,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            DB::table('sm_address_status')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_address_status');
    }
};
