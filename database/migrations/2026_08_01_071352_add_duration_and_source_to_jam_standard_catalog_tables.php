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
        Schema::table('jam_standard_songs', function (Blueprint $table) {
            $table->unsignedInteger('duration')->nullable()->after('notes');
            $table->string('source')->nullable()->after('duration');
        });

        Schema::table('jam_standard_song_requests', function (Blueprint $table) {
            $table->unsignedInteger('duration')->nullable()->after('notes');
            $table->string('source')->nullable()->after('duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_standard_song_requests', function (Blueprint $table) {
            $table->dropColumn(['duration', 'source']);
        });

        Schema::table('jam_standard_songs', function (Blueprint $table) {
            $table->dropColumn(['duration', 'source']);
        });
    }
};
