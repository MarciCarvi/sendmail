<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sm_campaign_sends', function (Blueprint $table) {
            $table->string('message_id')->nullable()->after('sent_at')->index();
            $table->timestamp('delivered_at')->nullable()->after('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_campaign_sends', function (Blueprint $table) {
            $table->dropColumn(['message_id', 'delivered_at']);
        });
    }
};
