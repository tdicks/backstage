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
        Schema::table('sets', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('jam_session_id');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('set_id');
        });

        Schema::table('slots', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('song_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
