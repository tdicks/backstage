<?php

use App\Models\Slot;

test('slot options include keys', function () {
    expect(Slot::options())
        ->toHaveKey('keys')
        ->and(Slot::options()['keys'])
        ->toBe('Keys');
});