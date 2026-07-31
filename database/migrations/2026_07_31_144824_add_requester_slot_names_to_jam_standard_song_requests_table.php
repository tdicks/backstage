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
        Schema::table('jam_standard_song_requests', function (Blueprint $table) {
            $table->json('requester_slot_names')->nullable()->after('slot_names');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_standard_song_requests', function (Blueprint $table) {
            $table->dropColumn('requester_slot_names');
        });
    }
};
