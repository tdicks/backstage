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
            $table->foreignId('band_template_id')
                ->nullable()
                ->after('song_id')
                ->constrained('band_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('song_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('band_template_id');
        });
    }
};