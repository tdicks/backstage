<?php

use App\Models\BandTemplate;
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