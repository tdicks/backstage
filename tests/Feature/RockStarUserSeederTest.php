<?php

use App\Models\User;
use Database\Seeders\RockStarUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('rock star user seeder creates reusable development accounts', function () {
    $this->seed(RockStarUserSeeder::class);
    $this->seed(RockStarUserSeeder::class);

    $james = User::query()->where('email', 'james@metallica.com')->firstOrFail();

    expect(User::query()->count())->toBe(30)
        ->and($james->name)->toBe('James Hetfield')
        ->and(Hash::check('password', $james->password))->toBeTrue()
        ->and($james->slotCoverageMap())->toBe(['vocals' => 'can', 'rhythm_guitar' => 'can']);
});
