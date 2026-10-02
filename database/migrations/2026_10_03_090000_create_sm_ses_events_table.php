<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro degli eventi SES (consegna, bounce, complaint, ritardi, rifiuti)
        Schema::create('sm_ses_events', function (Blueprint $table) {
            $table->id();
            $table->string('sns_message_id', 100)->nullable()->unique(); // evita doppioni se SNS ritenta
            $table->string('message_id')->index();                        // MessageId SES = sm_campaign_sends.message_id
            $table->string('event_type', 30);
            $table->string('bounce_type', 20)->nullable();                // Permanent / Transient / Undetermined
            $table->string('bounce_subtype', 40)->nullable();             // es. General, MailboxFull; per i complaint il tipo di feedback
            $table->string('recipient')->nullable();
            $table->text('diagnostic')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('applied_at')->nullable()->index();         // null = in attesa che l'invio salvi il message_id
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('sm_campaign_sends', function (Blueprint $table) {
            $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            $table->string('bounce_type', 20)->nullable()->after('bounced_at');
            $table->string('bounce_subtype', 40)->nullable()->after('bounce_type');
            $table->timestamp('complained_at')->nullable()->after('bounce_subtype');
        });

        // Allinea lo status degli iscritti: il double opt-in usa 'unconfirmed',
        // assente nella migration originale (sicuro da rieseguire).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `sm_subscribers` MODIFY `status` ENUM('subscribed','unsubscribed','bounced','complained','unconfirmed') NOT NULL DEFAULT 'subscribed'");
        }
    }

    public function down(): void
    {
        Schema::table('sm_campaign_sends', function (Blueprint $table) {
            $table->dropColumn(['bounced_at', 'bounce_type', 'bounce_subtype', 'complained_at']);
        });
        Schema::dropIfExists('sm_ses_events');
    }
};
