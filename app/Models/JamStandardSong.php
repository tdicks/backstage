<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class JamStandardSong extends Model
{
    protected $fillable = [
        'artist',
        'title',
        'notes',
        'band_template_id',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')
            ->withoutGlobalScope(User::ACTIVE_ACCOUNTS_SCOPE);
    }

    public function bandTemplate(): BelongsTo
    {
        return $this->belongsTo(BandTemplate::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function songRequests(): HasMany
    {
        return $this->hasMany(SongRequest::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(JamStandardSlot::class)->orderBy('position');
    }

    public function userSlots(): HasMany
    {
        return $this->hasMany(JamStandardUserSlot::class);
    }

    /**
     * @return Collection<int, self>
     */
    public static function nearMatchesFor(string $artist, string $title, int $limit = 3): Collection
    {
        $incomingArtist = self::normalizeComparableText($artist);
        $incomingTitle = self::normalizeComparableTitle($title);

        return self::query()
            ->active()
            ->get(['id', 'artist', 'title'])
            ->filter(function (self $candidate) use ($incomingArtist, $incomingTitle): bool {
                $candidateArtist = self::normalizeComparableText($candidate->artist);

                if ($candidateArtist !== $incomingArtist) {
                    return false;
                }

                $candidateTitle = self::normalizeComparableTitle($candidate->title);

                if ($candidateTitle === $incomingTitle) {
                    return true;
                }

                similar_text($incomingTitle, $candidateTitle, $titleSimilarity);

                return $titleSimilarity >= 82.0;
            })
            ->sortByDesc(function (self $candidate) use ($incomingTitle): float {
                $candidateTitle = self::normalizeComparableTitle($candidate->title);

                if ($candidateTitle === $incomingTitle) {
                    return 100.0;
                }

                similar_text($incomingTitle, $candidateTitle, $titleSimilarity);

                return $titleSimilarity;
            })
            ->take($limit)
            ->values();
    }

    public static function normalizeComparableTitle(string $value): string
    {
        $normalized = self::normalizeComparableText($value);

        // Remove release qualifiers so common variants collide meaningfully.
        $normalized = preg_replace('/\b(remaster(?:ed)?|live|version|mix|edit|acoustic|mono|stereo|demo|radio|instrumental|karaoke)\b/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public static function normalizeComparableText(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\([^)]*\)/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\[[^\]]*\]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(feat\.?|featuring|ft\.?)\b.*$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
