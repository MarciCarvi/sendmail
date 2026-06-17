<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('sm_lists')->cascadeOnDelete();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'bounced', 'complained'])->default('subscribed');
            $table->string('token', 64)->unique();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index('list_id');
            $table->index('email');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_subscribers');
    }
};
