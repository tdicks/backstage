<?php

namespace App\Services;

use App\Models\JamStandardSong;
use Illuminate\Support\Facades\Http;

class DeezerDurationLookup
{
    public function findDuration(string $artist, string $title): ?int
    {
        $response = Http::timeout(6)->get('https://api.deezer.com/search', [
            'q' => sprintf('artist:"%s" track:"%s"', $artist, $title),
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Deezer track lookup failed.');
        }

        $normalizedArtist = JamStandardSong::normalizeComparableText($artist);
        $normalizedTitle = JamStandardSong::normalizeComparableTitle($title);

        foreach ($response->json('data') ?? [] as $track) {
            $trackArtist = JamStandardSong::normalizeComparableText((string) ($track['artist']['name'] ?? ''));
            $trackTitle = JamStandardSong::normalizeComparableTitle((string) ($track['title'] ?? ''));
            $duration = (int) ($track['duration'] ?? 0);

            if ($trackArtist === $normalizedArtist && $trackTitle === $normalizedTitle && $duration > 0) {
                return $duration;
            }
        }

        return null;
    }
}
