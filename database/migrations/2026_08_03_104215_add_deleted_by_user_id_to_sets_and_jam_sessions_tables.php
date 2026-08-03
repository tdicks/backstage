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
            $table->foreignId('deleted_by_user_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->foreignId('deleted_by_user_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_user_id');
        });

        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_user_id');
        });
    }
};
