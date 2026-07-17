<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = 'jam_sessions';

    protected $fillable = [
        'name',
        'date',
        'description',
        'is_closed',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
