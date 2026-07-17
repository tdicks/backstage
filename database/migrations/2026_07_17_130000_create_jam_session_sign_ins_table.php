<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_session_sign_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jam_session_id')->constrained('jam_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('signed_in_at');
            $table->timestamps();

            $table->unique(['jam_session_id', 'user_id']);
            $table->index(['jam_session_id', 'signed_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_session_sign_ins');
    }
};
