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
        Schema::create('band_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_template_id')->constrained('band_templates')->cascadeOnDelete();
            $table->enum('name', ['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_template_slots');
    }
};
