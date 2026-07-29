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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploader_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 12);
            $table->string('label')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('disk', 64)->nullable();
            $table->string('path')->nullable();
            $table->text('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'created_at'], 'attachments_attachable_created_idx');
            $table->index(['type', 'created_at'], 'attachments_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
