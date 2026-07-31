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
        Schema::table('song_requests', function (Blueprint $table) {
            $table->foreignId('jam_standard_song_id')
                ->nullable()
                ->after('song_id')
                ->constrained('jam_standard_songs')
                ->nullOnDelete();
            $table->json('requested_slot_names')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jam_standard_song_id');
            $table->dropColumn('requested_slot_names');
        });
    }
};
