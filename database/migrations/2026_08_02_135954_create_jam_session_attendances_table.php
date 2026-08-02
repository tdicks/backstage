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
        Schema::create('jam_session_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jam_session_id')->constrained('jam_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('maybe');
            $table->string('source', 24)->default('self');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['jam_session_id', 'user_id']);
            $table->index(['jam_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jam_session_attendances');
    }
};
