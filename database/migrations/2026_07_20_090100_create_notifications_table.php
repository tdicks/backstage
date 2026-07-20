<?php

use App\Support\NotificationTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });

        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            foreach (['enabled', 'popup', 'email', 'text'] as $channel) {
                DB::table('settings')->updateOrInsert(
                    ['key' => NotificationTypeCatalog::adminSettingKey($type, $channel)],
                    [
                        'name' => $definition['label'].' '.str($channel)->replace('_', ' ')->title()->toString(),
                        'input_type' => 'checkbox',
                        'value' => $definition['defaults'][$channel] ? '1' : '0',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        foreach (NotificationTypeCatalog::definitions() as $type => $definition) {
            foreach (['enabled', 'popup', 'email', 'text'] as $channel) {
                DB::table('settings')
                    ->where('key', NotificationTypeCatalog::adminSettingKey($type, $channel))
                    ->delete();
            }
        }

        Schema::dropIfExists('notifications');
    }
};
