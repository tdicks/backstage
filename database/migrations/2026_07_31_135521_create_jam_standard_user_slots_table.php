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
        Schema::create('jam_standard_user_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jam_standard_song_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slot_name');
            $table->timestamps();

            $table->unique(['jam_standard_song_id', 'user_id', 'slot_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jam_standard_user_slots');
    }
};
