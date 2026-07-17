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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
