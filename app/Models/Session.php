<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    protected $table = 'jam_sessions';

    protected $fillable = [
        'name',
        'date',
        'description',
        'is_closed',
        'is_hidden',
        'is_archived',
        'allow_checkins',
        'is_live',
        'live_code',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
            'is_hidden' => 'boolean',
            'is_archived' => 'boolean',
            'allow_checkins' => 'boolean',
            'is_live' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Session $session): void {
            if ($session->is_closed) {
                $session->allow_checkins = false;
                $session->is_live = false;
            }
        });
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class)
            ->orderBy('position')
            ->orderBy('id');
    }
}
