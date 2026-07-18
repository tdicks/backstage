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
            DB::statement("ALTER TABLE slot_assignments MODIFY status ENUM('awaiting_target_consent', 'pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE slot_assignments DROP CONSTRAINT IF EXISTS slot_assignments_status_check');
            DB::statement("ALTER TABLE slot_assignments ADD CONSTRAINT slot_assignments_status_check CHECK (status IN ('awaiting_target_consent', 'pending', 'accepted', 'rejected'))");

            return;
        }

        if ($driver === 'sqlite' && Schema::hasTable('slot_assignments')) {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('slot_assignments', 'slot_assignments_old');

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

            DB::statement('INSERT INTO slot_assignments (id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at) SELECT id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at FROM slot_assignments_old');

            Schema::dropIfExists('slot_assignments_old');
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('slot_assignments')
            ->where('status', 'awaiting_target_consent')
            ->update(['status' => 'rejected']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE slot_assignments MODIFY status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE slot_assignments DROP CONSTRAINT IF EXISTS slot_assignments_status_check');
            DB::statement("ALTER TABLE slot_assignments ADD CONSTRAINT slot_assignments_status_check CHECK (status IN ('pending', 'accepted', 'rejected'))");

            return;
        }

        if ($driver === 'sqlite' && Schema::hasTable('slot_assignments')) {
            DB::statement('PRAGMA foreign_keys=OFF');

            Schema::rename('slot_assignments', 'slot_assignments_old');

            Schema::create('slot_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('slot_id')->constrained('slots')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('type', ['request', 'proposal']);
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->text('message')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO slot_assignments (id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at) SELECT id, slot_id, actor_user_id, target_user_id, type, status, message, responded_at, created_at, updated_at FROM slot_assignments_old');

            Schema::dropIfExists('slot_assignments_old');
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
