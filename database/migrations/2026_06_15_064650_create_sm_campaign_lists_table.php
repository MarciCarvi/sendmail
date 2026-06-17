<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_campaign_lists', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained('sm_campaigns')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('sm_lists')->cascadeOnDelete();
            $table->primary(['campaign_id', 'list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_campaign_lists');
    }
};
