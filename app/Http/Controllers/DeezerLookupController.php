<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeezerLookupController extends Controller
{
    public function artists(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'artists' => [],
            ]);
        }

        try {
            $cacheKey = 'deezer:artists:'.sha1(mb_strtolower($query));
            $artists = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query): array {
                return $this->fetchArtistsFromDeezer($query);
            });

            return response()->json([
                'artists' => $artists,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'artists' => [],
            ], 502);
        }
    }

    public function tracks(Request $request): JsonResponse
    {
        $artist = trim((string) $request->query('artist', ''));
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($artist) < 2 || mb_strlen($query) < 2) {
            return response()->json([
                'titles' => [],
            ]);
        }

        try {
            $cacheKey = 'deezer:tracks:'.sha1(mb_strtolower($artist).'|'.mb_strtolower($query));
            $titles = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($artist, $query): array {
                return $this->fetchTitlesFromDeezer($artist, $query);
            });

            return response()->json([
                'titles' => $titles,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'titles' => [],
            ], 502);
        }
    }

    private function fetchArtistsFromDeezer(string $query): array
    {
        $response = Http::timeout(6)
            ->get('https://api.deezer.com/search/artist', [
                'q' => $query,
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Deezer artist lookup failed.');
        }

        $seen = [];
        $artists = [];

        foreach (($response->json('data') ?? []) as $artist) {
            $name = $artist['name'] ?? null;

            if (! is_string($name) || $name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $artists[] = $name;

            if (count($artists) >= 8) {
                break;
            }
        }

        return $artists;
    }

    private function fetchTitlesFromDeezer(string $artist, string $query): array
    {
        $response = Http::timeout(6)
            ->get('https://api.deezer.com/search', [
                'q' => sprintf('artist:"%s" track:"%s"', $artist, $query),
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Deezer track lookup failed.');
        }

        $titles = $this->extractTrackTitles($response->json('data') ?? [], $artist, $query);

        if ($titles !== []) {
            return $titles;
        }

        // Fallback: looser query catches cases where Deezer metadata differs from typed artist.
        $fallbackResponse = Http::timeout(6)
            ->get('https://api.deezer.com/search', [
                'q' => sprintf('%s %s', $artist, $query),
            ]);

        if (! $fallbackResponse->ok()) {
            return [];
        }

        return $this->extractTrackTitles($fallbackResponse->json('data') ?? [], $artist, $query);
    }

    private function extractTrackTitles(array $tracks, string $artist, string $query): array
    {
        $normalizedArtist = $this->normalizeForMatch($artist);
        $normalizedQuery = $this->normalizeForMatch($query);
        $seen = [];
        $titles = [];

        foreach ($tracks as $track) {
            $trackArtist = $this->normalizeForMatch((string) ($track['artist']['name'] ?? ''));
            $title = $track['title'] ?? null;
            $normalizedTitle = $this->normalizeForMatch((string) $title);

            if (! is_string($title) || $title === '' || isset($seen[$title])) {
                continue;
            }

            if (! $this->artistLooksRelated($normalizedArtist, $trackArtist)) {
                continue;
            }

            if ($normalizedQuery !== '' && ! str_contains($normalizedTitle, $normalizedQuery)) {
                continue;
            }

            $seen[$title] = true;
            $duration = isset($track['duration']) ? (int) $track['duration'] : null;
            $titles[] = ['title' => $title, 'duration' => $duration];

            if (count($titles) >= 8) {
                break;
            }
        }

        return $titles;
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
    }

    private function artistLooksRelated(string $requestedArtist, string $trackArtist): bool
    {
        if ($requestedArtist === '' || $trackArtist === '') {
            return false;
        }

        if ($requestedArtist === $trackArtist) {
            return true;
        }

        return str_contains($requestedArtist, $trackArtist) || str_contains($trackArtist, $requestedArtist);
    }
}
