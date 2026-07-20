<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'is_hidden',
        'song_requests',
        'feature_set',
        'collaborator_ids',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'performed' => 'boolean',
            'signups_open' => 'boolean',
            'is_hidden' => 'boolean',
            'song_requests' => 'boolean',
            'feature_set' => 'boolean',
            'collaborator_ids' => 'array',
        ];
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->where('is_hidden', false);
        }

        if ($user?->is_admin) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('is_hidden', false)
                ->orWhere('owner_id', $user->id)
                ->orWhereJsonContains('collaborator_ids', $user->id);
        });
    }

    /**
     * Returns the collaborator user IDs for this set.
     *
     * @return array<int>
     */
    public function collaboratorUserIds(): array
    {
        return array_values(array_map('intval', $this->collaborator_ids ?? []));
    }

    /**
     * Determines whether the given user is a collaborator on this set.
     */
    public function isCollaborator(User $user): bool
    {
        return in_array($user->id, $this->collaboratorUserIds(), true);
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
