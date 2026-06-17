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
        Schema::table('sm_lists', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('id');
        });

        // Genera token per le liste esistenti (uso DB::table per evitare $fillable)
        foreach (\Illuminate\Support\Facades\DB::table('sm_lists')->whereNull('api_token')->get() as $list) {
            \Illuminate\Support\Facades\DB::table('sm_lists')
                ->where('id', $list->id)
                ->update(['api_token' => \Illuminate\Support\Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('sm_lists', function (Blueprint $table) {
            $table->dropColumn('api_token');
        });
    }
};
