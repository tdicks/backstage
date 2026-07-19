<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Set extends Model
{
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'jam_session_id',
        'position',
        'performed',
        'signups_open',
        'song_requests',
        'feature_set',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'performed' => 'boolean',
            'signups_open' => 'boolean',
            'song_requests' => 'boolean',
            'feature_set' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(JamSession::class, 'jam_session_id');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class)
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

    public function songRequests(): HasMany
    {
        return $this->hasMany(SongRequest::class);
    }
}
