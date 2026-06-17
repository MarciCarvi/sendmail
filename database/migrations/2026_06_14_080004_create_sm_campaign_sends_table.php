<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('sm_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('sm_subscribers')->cascadeOnDelete();
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced', 'complained'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_campaign_sends');
    }
};
