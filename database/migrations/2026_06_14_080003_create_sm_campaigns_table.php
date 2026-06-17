<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('sm_lists')->cascadeOnDelete();
            $table->string('subject');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->longText('html_content')->nullable();
            $table->text('text_content')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'paused'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_campaigns');
    }
};
