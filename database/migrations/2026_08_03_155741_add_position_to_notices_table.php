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
        Schema::table('notices', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('location');
            $table->index(['location', 'position']);
        });

        foreach (['above_nav', 'below_nav', 'below_header'] as $location) {
            $noticeIds = DB::table('notices')
                ->where('location', $location)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            foreach ($noticeIds as $index => $noticeId) {
                DB::table('notices')
                    ->where('id', $noticeId)
                    ->update(['position' => $index + 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropIndex(['location', 'position']);
            $table->dropColumn('position');
        });
    }
};
