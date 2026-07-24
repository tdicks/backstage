<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE slots MODIFY name VARCHAR(64) NOT NULL');
            DB::statement('ALTER TABLE band_template_slots MODIFY name VARCHAR(64) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE slots DROP CONSTRAINT IF EXISTS slots_name_check');
            DB::statement('ALTER TABLE slots ALTER COLUMN name TYPE VARCHAR(64)');
            DB::statement('ALTER TABLE band_template_slots DROP CONSTRAINT IF EXISTS band_template_slots_name_check');
            DB::statement('ALTER TABLE band_template_slots ALTER COLUMN name TYPE VARCHAR(64)');

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTables();
        }
    }

    public function down(): void
    {
        // Dynamic values cannot be safely narrowed back to the legacy enum.
    }

    private function rebuildSqliteTables(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::rename('slot_assignments', 'slot_assignments_dynamic_types_old');
        Schema::rename('slots', 'slots_dynamic_types_old');
        Schema::rename('band_template_slots', 'band_template_slots_dynamic_types_old');

        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->string('name', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manual_performer_name')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('slot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('slots')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['request', 'proposal']);
            $table->enum('status', ['awaiting_target_consent', 'pending', 'accepted', 'rejected'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('band_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_template_id')->constrained('band_templates')->cascadeOnDelete();
            $table->string('name', 64);
            $table->timestamps();
        });

        DB::statement('INSERT INTO slots (id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at) SELECT id, song_id, name, user_id, manual_performer_name, position, created_at, updated_at FROM slots_dynamic_types_old');
        DB::statement('INSERT INTO slot_assignments (id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at) SELECT id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at FROM slot_assignments_dynamic_types_old');
        DB::statement('INSERT INTO band_template_slots (id, band_template_id, name, created_at, updated_at) SELECT id, band_template_id, name, created_at, updated_at FROM band_template_slots_dynamic_types_old');

        Schema::drop('slot_assignments_dynamic_types_old');
        Schema::drop('slots_dynamic_types_old');
        Schema::drop('band_template_slots_dynamic_types_old');

        DB::statement('PRAGMA foreign_keys=ON');
    }
};
