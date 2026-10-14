<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Il webhook cercava l'iscritto con l'indirizzo "Nome <a@b.it>" riportato da SES e non lo trovava:
     * i bounce permanenti e i reclami restavano "subscribed". Riallinea lo status usando gli esiti
     * già registrati sugli invii. Non tocca le righe modificate dopo l'evento (es. riabilitate a mano)
     * né quelle create dopo. Solo dati, nessun cambio di schema.
     */
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // reclami prima (più gravi), poi i bounce permanenti
        $this->realign('complained_at', 'complained', '1 = 1');
        $this->realign('bounced_at', 'bounced', "cs.bounce_type = 'Permanent'");
    }

    private function realign(string $column, string $status, string $condition): void
    {
        DB::update("
            UPDATE `sm_subscribers` s
            JOIN (
                SELECT b.email, MAX(cs.`{$column}`) AS happened_at
                FROM `sm_campaign_sends` cs
                JOIN `sm_subscribers` b ON b.id = cs.subscriber_id
                WHERE cs.`{$column}` IS NOT NULL AND {$condition}
                GROUP BY b.email
            ) x ON x.email = s.email
            SET s.status = ?, s.updated_at = ?
            WHERE s.status = 'subscribed'
              AND (s.updated_at IS NULL OR s.updated_at <= x.happened_at)
        ", [$status, now()]);
    }

    public function down(): void
    {
        // dati: nessun ripristino
    }
};
