<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Set extends Model
{
    use SoftDeletes;

    public const LIFECYCLE_DRAFT = 'draft';

    public const LIFECYCLE_SCHEDULED = 'scheduled';

    public const LIFECYCLE_PERFORMED = 'performed';

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'jam_session_id',
        'lifecycle_state',
        'position',
        'performed',
        'signups_open',
        'is_hidden',
        'song_requests',
        'feature_set',
        'free_for_all',
        'collaborator_ids',
        'candidate_session_ids',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'lifecycle_state' => 'string',
            'performed' => 'boolean',
            'signups_open' => 'boolean',
            'is_hidden' => 'boolean',
            'song_requests' => 'boolean',
            'feature_set' => 'boolean',
            'free_for_all' => 'boolean',
            'collaborator_ids' => 'array',
            'candidate_session_ids' => 'array',
            'deleted_by_user_id' => 'integer',
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

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lifecycle_state', self::LIFECYCLE_DRAFT)
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('lifecycle_state')
                        ->whereNull('jam_session_id');
                });
        });
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lifecycle_state', self::LIFECYCLE_SCHEDULED)
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('lifecycle_state')
                        ->where('performed', false)
                        ->whereNotNull('jam_session_id');
                });
        });
    }

    public function scopeForSetLibrary(Builder $query, User $user): Builder
    {
        if ($user->is_admin) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereJsonContains('collaborator_ids', $user->id);
        });
    }

    public function isDraft(): bool
    {
        return ($this->lifecycle_state ?? self::LIFECYCLE_DRAFT) === self::LIFECYCLE_DRAFT;
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
        return $this->belongsTo(User::class, 'owner_id')
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE);
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }
}
