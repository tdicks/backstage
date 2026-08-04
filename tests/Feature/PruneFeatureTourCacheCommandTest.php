<?php

use App\Support\FeatureTourConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('cache')) {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
    }
});

test('feature tour cache prune command deletes merged-config keys in database store', function () {
    $store = 'database';
    $prunableKey = FeatureTourConfig::MERGED_CONFIG_CACHE_KEY_PREFIX.'test-prune-1';
    $prunableKeyTwo = FeatureTourConfig::MERGED_CONFIG_CACHE_KEY_PREFIX.'test-prune-2';
    $otherKey = 'feature-tours:other-cache:test-prune';

    Cache::store($store)->put($prunableKey, ['value' => 1], 600);
    Cache::store($store)->put($prunableKeyTwo, ['value' => 2], 600);
    Cache::store($store)->put($otherKey, ['value' => 3], 600);

    expect(Cache::store($store)->has($prunableKey))->toBeTrue()
        ->and(Cache::store($store)->has($prunableKeyTwo))->toBeTrue()
        ->and(Cache::store($store)->has($otherKey))->toBeTrue();

    $exitCode = Artisan::call('app:prune-feature-tour-cache', [
        '--store' => $store,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Cache::store($store)->has($prunableKey))->toBeFalse()
        ->and(Cache::store($store)->has($prunableKeyTwo))->toBeFalse()
        ->and(Cache::store($store)->has($otherKey))->toBeTrue();

    Cache::store($store)->forget($otherKey);
});

test('feature tour cache prune command supports dry-run mode', function () {
    $store = 'database';
    $prunableKey = FeatureTourConfig::MERGED_CONFIG_CACHE_KEY_PREFIX.'test-prune-dry-run';

    Cache::store($store)->put($prunableKey, ['value' => 1], 600);

    expect(Cache::store($store)->has($prunableKey))->toBeTrue();

    $exitCode = Artisan::call('app:prune-feature-tour-cache', [
        '--store' => $store,
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Cache::store($store)->has($prunableKey))->toBeTrue();

    Cache::store($store)->forget($prunableKey);
});

test('feature tour cache prune command reports unsupported stores', function () {
    $exitCode = Artisan::call('app:prune-feature-tour-cache', [
        '--store' => 'array',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(2)
        ->and($output)->toContain('is not supported for key iteration pruning');
});
