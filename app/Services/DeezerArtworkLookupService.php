<?php

namespace App\Services;

use App\Models\JamStandardSong;
use App\Models\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeezerArtworkLookupService
{
    /**
     * @return list<array{url: string|null, label: string}>
     */
    public function artworkTilesForSet(Set $set, int $maxTiles = 4): array
    {
        $songs = $set->songs->take($maxTiles)->values();

        if ($songs->isEmpty()) {
            return [
                [
                    'url' => null,
                    'label' => $set->name,
                ],
            ];
        }

        $setSignature = $songs
            ->map(fn ($song): string => mb_strtolower((string) $song->artist).'|'.mb_strtolower((string) $song->title))
            ->implode(';');

        $cacheKey = 'deezer:set-artwork:'.$set->id.':'.sha1($setSignature);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($songs): array {
            return $songs
                ->map(function ($song): array {
                    $artist = trim((string) $song->artist);
                    $title = trim((string) $song->title);

                    return [
                        'url' => $this->coverUrlForSong($artist, $title),
                        'label' => $artist.' - '.$title,
                    ];
                })
                ->values()
                ->all();
        });
    }

    private function coverUrlForSong(string $artist, string $title): ?string
    {
        if ($artist === '' || $title === '') {
            return null;
        }

        if (app()->runningUnitTests()) {
            return null;
        }

        $normalizedArtist = JamStandardSong::normalizeComparableText($artist);
        $normalizedTitle = JamStandardSong::normalizeComparableTitle($title);

        if ($normalizedArtist === '' || $normalizedTitle === '') {
            return null;
        }

        $cacheKey = 'deezer:song-artwork:'.sha1($normalizedArtist.'|'.$normalizedTitle);

        return Cache::remember($cacheKey, now()->addDays(14), function () use ($artist, $title, $normalizedArtist, $normalizedTitle): ?string {
            return $this->lookupArtworkUrl($artist, $title, $normalizedArtist, $normalizedTitle);
        });
    }

    private function lookupArtworkUrl(string $artist, string $title, string $normalizedArtist, string $normalizedTitle): ?string
    {
        try {
            $strict = Http::timeout(6)->get('https://api.deezer.com/search', [
                'q' => sprintf('artist:"%s" track:"%s"', $artist, $title),
            ]);

            if ($strict->ok()) {
                $url = $this->extractArtworkUrl($strict->json('data') ?? [], $normalizedArtist, $normalizedTitle, true);
                if ($url !== null) {
                    return $url;
                }
            }

            $fallback = Http::timeout(6)->get('https://api.deezer.com/search', [
                'q' => sprintf('%s %s', $artist, $title),
            ]);

            if (! $fallback->ok()) {
                return null;
            }

            return $this->extractArtworkUrl($fallback->json('data') ?? [], $normalizedArtist, $normalizedTitle, false);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $tracks
     */
    private function extractArtworkUrl(array $tracks, string $normalizedArtist, string $normalizedTitle, bool $strict): ?string
    {
        foreach ($tracks as $track) {
            $trackArtist = JamStandardSong::normalizeComparableText((string) ($track['artist']['name'] ?? ''));
            $trackTitle = JamStandardSong::normalizeComparableTitle((string) ($track['title'] ?? ''));

            if ($strict) {
                if ($trackArtist !== $normalizedArtist || $trackTitle !== $normalizedTitle) {
                    continue;
                }
            } else {
                if ($trackArtist === '' || $trackTitle === '') {
                    continue;
                }

                $artistRelated = str_contains($trackArtist, $normalizedArtist) || str_contains($normalizedArtist, $trackArtist);
                $titleRelated = str_contains($trackTitle, $normalizedTitle) || str_contains($normalizedTitle, $trackTitle);

                if (! $artistRelated || ! $titleRelated) {
                    continue;
                }
            }

            $album = $track['album'] ?? [];
            $candidates = [
                $album['cover_medium'] ?? null,
                $album['cover_big'] ?? null,
                $album['cover'] ?? null,
                $album['cover_small'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
