<?php

use App\Models\JamStandardSong;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the catalog duration backfill updates only catalog songs with matching missing durations', function () {
    $missingDuration = JamStandardSong::query()->create([
        'artist' => 'The Cure',
        'title' => 'Just Like Heaven',
        'is_active' => true,
    ]);
    $existingDuration = JamStandardSong::query()->create([
        'artist' => 'The Cure',
        'title' => 'Pictures of You',
        'duration' => 435,
        'source' => 'deezer',
        'is_active' => true,
    ]);

    Http::fake([
        'https://api.deezer.com/search*' => Http::response([
            'data' => [[
                'title' => 'Just Like Heaven (Remastered)',
                'duration' => 212,
                'artist' => ['name' => 'The Cure'],
            ]],
        ]),
    ]);

    Artisan::call('catalog:backfill-catalog-durations', ['service' => 'deezer', '--delay' => 0]);

    expect($missingDuration->refresh())
        ->duration->toBe(212)
        ->source->toBe('deezer');
    expect($existingDuration->refresh())
        ->duration->toBe(435)
        ->source->toBe('deezer');
    expect(Artisan::output())->toContain('Complete: 1 updated, 0 unmatched, 0 failed.');

    Http::assertSentCount(1);
});

test('the catalog duration backfill dry run does not persist matches', function () {
    $song = JamStandardSong::query()->create([
        'artist' => 'Joy Division',
        'title' => 'Transmission',
        'is_active' => true,
    ]);

    Http::fake([
        'https://api.deezer.com/search*' => Http::response([
            'data' => [[
                'title' => 'Transmission',
                'duration' => 217,
                'artist' => ['name' => 'Joy Division'],
            ]],
        ]),
    ]);

    Artisan::call('catalog:backfill-catalog-durations', ['service' => 'deezer', '--delay' => 0, '--dry-run' => true]);

    expect($song->refresh()->duration)->toBeNull()
        ->and(Artisan::output())->toContain('Would update Joy Division - Transmission (217s).');
});

test('the catalog duration backfill rejects unsupported services', function () {
    Http::fake();

    $exitCode = Artisan::call('catalog:backfill-catalog-durations', ['service' => 'spotify', '--delay' => 0]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('Unsupported catalog service: spotify. Supported services: deezer.');

    Http::assertNothingSent();
});
