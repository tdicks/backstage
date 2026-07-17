<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class JamSession extends Model
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

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->is_admin) {
            return $query;
        }

        return $query->where('is_hidden', false);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function signIns(): HasMany
    {
        return $this->hasMany(JamSessionSignIn::class)->latest('signed_in_at');
    }
}
