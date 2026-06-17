<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_campaign_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('sm_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('sm_subscribers')->cascadeOnDelete();
            $table->text('original_url');
            $table->timestamp('clicked_at');
            $table->string('ip', 45)->nullable();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_campaign_clicks');
    }
};
