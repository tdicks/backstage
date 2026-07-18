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
        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->boolean('allow_checkins')->default(false)->after('is_archived');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->dropColumn('allow_checkins');
        });
    }
};
