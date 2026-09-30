<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->string('editor_mode', 10)->default('visual')->after('sender_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });
    }
};
