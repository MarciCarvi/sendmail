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
        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->longText('design_json')->nullable()->after('html_content');
        });
    }

    public function down(): void
    {
        Schema::table('sm_campaigns', function (Blueprint $table) {
            $table->dropColumn('design_json');
        });
    }
};
