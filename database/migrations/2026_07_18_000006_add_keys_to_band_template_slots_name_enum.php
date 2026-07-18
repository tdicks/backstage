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
            DB::statement("ALTER TABLE band_template_slots MODIFY name ENUM('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE band_template_slots DROP CONSTRAINT IF EXISTS band_template_slots_name_check");
            DB::statement("ALTER TABLE band_template_slots ADD CONSTRAINT band_template_slots_name_check CHECK (name IN ('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('band_template_slots', 'band_template_slots_old');

            Schema::create('band_template_slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('band_template_id')->constrained('band_templates')->cascadeOnDelete();
                $table->enum('name', ['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'keys', 'other']);
                $table->timestamps();
            });

            DB::statement('INSERT INTO band_template_slots (id, band_template_id, name, created_at, updated_at) SELECT id, band_template_id, name, created_at, updated_at FROM band_template_slots_old');
            Schema::dropIfExists('band_template_slots_old');
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
            DB::statement("ALTER TABLE band_template_slots MODIFY name ENUM('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other') NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE band_template_slots DROP CONSTRAINT IF EXISTS band_template_slots_name_check");
            DB::statement("ALTER TABLE band_template_slots ADD CONSTRAINT band_template_slots_name_check CHECK (name IN ('vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other'))");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('band_template_slots', 'band_template_slots_old');

            Schema::create('band_template_slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('band_template_id')->constrained('band_templates')->cascadeOnDelete();
                $table->enum('name', ['vocals', 'lead_guitar', 'rhythm_guitar', 'bass', 'drums', 'other']);
                $table->timestamps();
            });

            DB::statement('INSERT INTO band_template_slots (id, band_template_id, name, created_at, updated_at) SELECT id, band_template_id, name, created_at, updated_at FROM band_template_slots_old');
            Schema::dropIfExists('band_template_slots_old');
            DB::statement('PRAGMA foreign_keys=ON');
            return;
        }
    }
};