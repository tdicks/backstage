<?php

namespace App\Console\Commands;

use App\Models\JamStandardSong;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('catalog:backfill-deezer-durations
    {--limit= : Maximum number of catalog songs to process}
    {--delay=800 : Milliseconds to pause between Deezer requests}
    {--dry-run : Show matches without updating catalog songs}')]
#[Description('Backfill missing Jam Standards durations from Deezer')]
class BackfillJamStandardDurations extends Command
{
    private const DEEZER_SOURCE = 'deezer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit');
        $delayMilliseconds = max(0, (int) $this->option('delay'));
        $dryRun = (bool) $this->option('dry-run');

        $songs = JamStandardSong::query()
            ->active()
            ->where(fn ($query) => $query->whereNull('duration')->orWhere('duration', '<=', 0))
            ->orderBy('id');

        if ($limit !== null && $limit !== '') {
            $songs->limit(max(0, (int) $limit));
        }

        $updated = 0;
        $unmatched = 0;
        $failed = 0;

        foreach ($songs->cursor() as $song) {
            try {
                $duration = $this->findDuration($song);
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Could not look up {$song->artist} - {$song->title}.");
                $this->pause($delayMilliseconds);

                continue;
            }

            if ($duration === null) {
                $unmatched++;
                $this->line("No Deezer duration found for {$song->artist} - {$song->title}.");
            } else {
                if (! $dryRun) {
                    $song->update([
                        'duration' => $duration,
                        'source' => self::DEEZER_SOURCE,
                    ]);
                }

                $updated++;
                $action = $dryRun ? 'Would update' : 'Updated';
                $this->info("{$action} {$song->artist} - {$song->title} ({$duration}s).");
            }

            $this->pause($delayMilliseconds);
        }

        $this->newLine();
        $this->info("Complete: {$updated} updated, {$unmatched} unmatched, {$failed} failed.");

        return self::SUCCESS;
    }

    private function findDuration(JamStandardSong $song): ?int
    {
        $response = Http::timeout(6)->get('https://api.deezer.com/search', [
            'q' => sprintf('artist:"%s" track:"%s"', $song->artist, $song->title),
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Deezer track lookup failed.');
        }

        $artist = JamStandardSong::normalizeComparableText($song->artist);
        $title = JamStandardSong::normalizeComparableTitle($song->title);

        foreach ($response->json('data') ?? [] as $track) {
            $trackArtist = JamStandardSong::normalizeComparableText((string) ($track['artist']['name'] ?? ''));
            $trackTitle = JamStandardSong::normalizeComparableTitle((string) ($track['title'] ?? ''));
            $duration = (int) ($track['duration'] ?? 0);

            if ($trackArtist === $artist && $trackTitle === $title && $duration > 0) {
                return $duration;
            }
        }

        return null;
    }

    private function pause(int $delayMilliseconds): void
    {
        if ($delayMilliseconds > 0) {
            usleep($delayMilliseconds * 1000);
        }
    }
}
