<?php

use App\Models\JamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('live jam session page shows a live notice', function () {
    $user = User::factory()->create();
    $session = JamSession::create([
        'name' => 'Live Notice Jam',
        'date' => now()->addDay(),
        'description' => null,
        'is_live' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('sessions.show', $session))
        ->assertOk()
        ->assertSee('This jam session is now live')
        ->assertSee('Any changes to sets will update here in real time.');

    $view = file_get_contents(resource_path('views/sessions/show.blade.php'));

    expect($response->getContent())
        ->and(substr_count($response->getContent(), 'This jam session is now live'))->toBe(1);

    expect($view)
        ->toContain('grid w-full grid-cols-[1fr_auto_1fr] items-center rounded-md border border-emerald-700')
        ->toContain('class="h-5 w-5 justify-self-end"')
        ->toContain('<div class="text-center">')
        ->toContain('text-xs font-semibold uppercase tracking-widest')
        ->toContain('mt-1 text-xs text-emerald-200')
        ->toContain('class="justify-self-start"');
});
