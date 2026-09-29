<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_sender_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('from_name', 100);
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            $table->string('configuration_set', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_profile_id')->nullable()->after('reply_to');
            $table->foreign('sender_profile_id')->references('id')->on('sm_sender_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['sender_profile_id']);
            $table->dropColumn('sender_profile_id');
        });
        Schema::dropIfExists('sm_sender_profiles');
    }
};
