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
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('level', 24);
            $table->string('location', 24);
            $table->boolean('show_on_all_pages')->default(true);
            $table->json('show_on_routes')->nullable();
            $table->boolean('dismissable')->default(true);
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->index(['enabled', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
