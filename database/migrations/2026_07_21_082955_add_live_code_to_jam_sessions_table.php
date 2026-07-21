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
            $table->string('live_code', 4)->nullable()->unique()->after('is_live');
        });

        DB::table('jam_sessions')
            ->whereNull('live_code')
            ->orderBy('id')
            ->eachById(function (object $session): void {
                do {
                    $code = Str::random(4);
                } while (DB::table('jam_sessions')->where('live_code', $code)->exists());

                DB::table('jam_sessions')
                    ->where('id', $session->id)
                    ->update(['live_code' => $code]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_sessions', function (Blueprint $table) {
            $table->dropColumn('live_code');
        });
    }
};
