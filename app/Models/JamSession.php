<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JamSession extends Model
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
        'jam_manager_id',
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
            'jam_manager_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JamSession $session): void {
            $session->live_code ??= self::makeLiveCode();
        });

        static::saving(function (JamSession $session): void {
            if ($session->is_closed) {
                $session->allow_checkins = false;
                $session->is_live = false;
            }

            $session->live_code ??= self::makeLiveCode($session->id);
        });
    }

    public static function makeLiveCode(?int $ignoreId = null): string
    {
        do {
            $code = Str::random(4);
            $query = self::query()->where('live_code', $code);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
        } while ($query->exists());

        return $code;
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
            ->orderByDesc('feature_set')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function getRouteKey(): string
    {
        return $this->routeSlug();
    }

    public function routeSlug(): string
    {
        return $this->id.'-'.Str::slug($this->name);
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        $id = Str::before((string) $value, '-');

        if (! ctype_digit($id)) {
            return null;
        }

        return $this->whereKey((int) $id)->first();
    }

    public function signIns(): HasMany
    {
        return $this->hasMany(JamSessionSignIn::class)->latest('signed_in_at');
    }

    public function jamManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jam_manager_id');
    }
}
