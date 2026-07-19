<?php

use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view slot conflict management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.slot-conflicts.index'))
        ->assertOk()
        ->assertSee('Slot Conflicts')
        ->assertSee('Lead Guitar')
        ->assertSee('Bass')
        ->assertSee('Changes are saved immediately')
        ->assertDontSee('Save Row');
});

test('non admin cannot view slot conflict management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.slot-conflicts.index'))
        ->assertForbidden();
});

test('admin can update slot conflicts symmetrically', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $vocals = SlotType::query()->where('key', 'vocals')->firstOrFail();
    $bass = SlotType::query()->where('key', 'bass')->firstOrFail();

    $this->actingAs($admin)
        ->patchJson(route('admin.slot-conflicts.update', $vocals), [
            'conflict_id' => $bass->id,
            'enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('slot_type_id', $vocals->id)
        ->assertJsonPath('conflicting_slot_type_id', $bass->id);

    $this->assertDatabaseHas('slot_type_conflicts', [
        'slot_type_id' => $vocals->id,
        'conflicting_slot_type_id' => $bass->id,
    ]);

    $this->assertDatabaseHas('slot_type_conflicts', [
        'slot_type_id' => $bass->id,
        'conflicting_slot_type_id' => $vocals->id,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.slot-conflicts.update', $vocals), [
            'conflict_id' => $bass->id,
            'enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    $this->assertDatabaseMissing('slot_type_conflicts', [
        'slot_type_id' => $vocals->id,
        'conflicting_slot_type_id' => $bass->id,
    ]);

    $this->assertDatabaseMissing('slot_type_conflicts', [
        'slot_type_id' => $bass->id,
        'conflicting_slot_type_id' => $vocals->id,
    ]);
});
