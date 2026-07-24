<?php

use App\Models\BandTemplate;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create a band template with a keys slot', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('band-templates.store'), [
            'name' => 'Keys Template',
            'slot_names' => ['vocals', 'keys'],
        ])
        ->assertRedirect();

    $template = BandTemplate::query()->where('name', 'Keys Template')->firstOrFail();

    expect($template->slots()->pluck('name')->all())->toBe(['vocals', 'keys']);
});

test('admin can create a band template with a custom slot type', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    SlotType::query()->create([
        'key' => 'sick_licks',
        'name' => 'Sick Licks',
        'sort_order' => 80,
        'active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('band-templates.store'), [
            'name' => 'Custom Template',
            'slot_names' => ['sick_licks'],
        ])
        ->assertRedirect();

    $template = BandTemplate::query()->where('name', 'Custom Template')->firstOrFail();

    expect($template->slots()->pluck('name')->all())->toBe(['sick_licks']);
});

test('band templates page links to slot conflict configuration', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('band-templates.index'))
        ->assertOk()
        ->assertSee('Band Templates')
        ->assertSee('Slot Conflicts')
        ->assertSee(route('admin.slot-conflicts.index'), false);
});
