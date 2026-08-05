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
        Schema::table('sets', function (Blueprint $table) {
            $table->foreignId('jam_session_id')->nullable()->change();
            $table->string('lifecycle_state', 24)->default('scheduled')->after('jam_session_id');
        });

        DB::table('sets')
            ->whereNotNull('jam_session_id')
            ->where('performed', false)
            ->update(['lifecycle_state' => 'scheduled']);

        DB::table('sets')
            ->whereNotNull('jam_session_id')
            ->where('performed', true)
            ->update(['lifecycle_state' => 'performed']);

        DB::table('sets')
            ->whereNull('jam_session_id')
            ->update(['lifecycle_state' => 'draft']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fallbackSessionId = DB::table('jam_sessions')->orderBy('id')->value('id');

        if ($fallbackSessionId !== null) {
            DB::table('sets')
                ->whereNull('jam_session_id')
                ->update(['jam_session_id' => $fallbackSessionId]);
        } else {
            DB::table('sets')
                ->whereNull('jam_session_id')
                ->delete();
        }

        Schema::table('sets', function (Blueprint $table) {
            $table->dropColumn('lifecycle_state');
            $table->foreignId('jam_session_id')->nullable(false)->change();
        });
    }
};
