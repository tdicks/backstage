<?php

use App\Services\VapidKeyGenerator;
use Illuminate\Support\Facades\Artisan;

test('vapid generate command prints env-ready values', function () {
    $mock = Mockery::mock(VapidKeyGenerator::class);
    $mock->shouldReceive('generate')->once()->andReturn([
        'publicKey' => 'test-public-key',
        'privateKey' => 'test-private-key',
    ]);
    app()->instance(VapidKeyGenerator::class, $mock);

    Artisan::call('app:vapid-generate', [
        '--subject' => 'mailto:test@example.com',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('WEBPUSH_VAPID_SUBJECT=mailto:test@example.com')
        ->and($output)->toContain('WEBPUSH_VAPID_PUBLIC_KEY=')
        ->and($output)->toContain('WEBPUSH_VAPID_PRIVATE_KEY=');
});

test('vapid generate command can output json', function () {
    $mock = Mockery::mock(VapidKeyGenerator::class);
    $mock->shouldReceive('generate')->once()->andReturn([
        'publicKey' => 'json-public-key',
        'privateKey' => 'json-private-key',
    ]);
    app()->instance(VapidKeyGenerator::class, $mock);

    Artisan::call('app:vapid-generate', [
        '--subject' => 'mailto:test@example.com',
        '--json' => true,
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys(['subject', 'publicKey', 'privateKey'])
        ->and($decoded['subject'])->toBe('mailto:test@example.com')
        ->and($decoded['publicKey'])->toBe('json-public-key')
        ->and($decoded['privateKey'])->toBe('json-private-key');
});
