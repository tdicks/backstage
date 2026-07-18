<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE slots MODIFY name ENUM('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE slots DROP CONSTRAINT IF EXISTS slots_name_check");
            DB::statement("ALTER TABLE slots ADD CONSTRAINT slots_name_check CHECK (name IN ('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('slots', 'slots_old');

            Schema::create('slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
                $table->enum('name', ['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other']);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('manual_performer_name')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });

            DB::statement('INSERT INTO slots (id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at) SELECT id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at FROM slots_old');
            Schema::dropIfExists('slots_old');
            DB::statement('PRAGMA foreign_keys=ON');
            return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE slots MODIFY name ENUM('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE slots DROP CONSTRAINT IF EXISTS slots_name_check");
            DB::statement("ALTER TABLE slots ADD CONSTRAINT slots_name_check CHECK (name IN ('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('slots', 'slots_old');

            Schema::create('slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
                $table->enum('name', ['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other']);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('manual_performer_name')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });

            DB::statement('INSERT INTO slots (id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at) SELECT id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at FROM slots_old');
            Schema::dropIfExists('slots_old');
            DB::statement('PRAGMA foreign_keys=ON');
            return;
        }
    }
};