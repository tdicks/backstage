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
        Schema::table('jam_sessions', function (Blueprint $table): void {
            $table->foreignId('jam_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('live_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('jam_manager_id');
        });
    }
};
