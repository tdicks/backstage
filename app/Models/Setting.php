<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'input_type', 'value'])]
class Setting extends Model
{
    public const INPUT_TYPES = [
        'text',
        'textarea',
        'number',
        'email',
        'url',
        'password',
        'checkbox',
        'select',
        'date',
        'time',
        'datetime-local',
        'color',
    ];

    public function isEnabled(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOL);
    }

    public static function enabled(string $key, bool $default = false): bool
    {
        $setting = self::query()->where('key', $key)->first();

        return $setting ? $setting->isEnabled() : $default;
    }
}
