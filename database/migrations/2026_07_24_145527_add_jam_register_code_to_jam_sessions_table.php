<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->string('jam_register_code', 4)->nullable()->unique()->after('live_code');
        });

        $sessionIds = DB::table('jam_sessions')->pluck('id');

        foreach ($sessionIds as $sessionId) {
            do {
                $code = Str::random(4);
            } while (DB::table('jam_sessions')->where('jam_register_code', $code)->exists());

            DB::table('jam_sessions')
                ->where('id', $sessionId)
                ->update(['jam_register_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->dropUnique(['jam_register_code']);
            $table->dropColumn('jam_register_code');
        });
    }
};
