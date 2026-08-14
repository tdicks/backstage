<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('capture command stores a users dashboard layout as the default', function () {
    $layout = [
        ['id' => 'action-inbox', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
        ['id' => 'coming-up', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 3],
    ];
    $user = User::factory()->create([
        'dashboard_widget_layouts' => $layout,
    ]);

    $exitCode = Artisan::call('app:capture-dashboard-default-layout', [
        'user' => $user->email,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain("Captured dashboard layout from user {$user->id}")
        ->and(Setting::query()->where('key', Setting::DASHBOARD_DEFAULT_WIDGET_LAYOUT_KEY)->value('value'))
        ->toBe(json_encode($layout, JSON_THROW_ON_ERROR));
});

test('capture command rejects users without a saved dashboard layout', function () {
    $user = User::factory()->create();

    $exitCode = Artisan::call('app:capture-dashboard-default-layout', [
        'user' => (string) $user->id,
    ]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain("User {$user->id} ({$user->email}) has no saved dashboard layout.");
});
