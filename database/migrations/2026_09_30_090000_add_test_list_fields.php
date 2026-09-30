<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_lists', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('double_optin');
        });

        Schema::table('sm_sender_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('test_list_id')->nullable()->after('configuration_set');
            $table->foreign('test_list_id')->references('id')->on('sm_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sm_sender_profiles', function (Blueprint $table) {
            $table->dropForeign(['test_list_id']);
            $table->dropColumn('test_list_id');
        });

        Schema::table('sm_lists', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
