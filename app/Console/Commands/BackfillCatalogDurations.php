<?php

namespace App\Console\Commands;

use App\Models\JamStandardSong;
use App\Services\DeezerDurationLookup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:backfill-catalog-durations
    {service : Catalog service to use for duration lookups}
    {--limit= : Maximum number of catalog songs to process}
    {--delay=800 : Milliseconds to pause between service requests}
    {--dry-run : Show matches without updating catalog songs}')]
#[Description('Backfill missing catalog song durations from a service')]
class BackfillCatalogDurations extends Command
{
    private const SUPPORTED_SERVICES = ['deezer'];

    public function __construct(private DeezerDurationLookup $deezerDurationLookup)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = (string) $this->argument('service');

        if (! in_array($service, self::SUPPORTED_SERVICES, true)) {
            $this->error("Unsupported catalog service: {$service}. Supported services: ".implode(', ', self::SUPPORTED_SERVICES).'.');

            return self::INVALID;
        }

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
                $duration = $this->deezerDurationLookup->findDuration($song->artist, $song->title);
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Could not look up {$song->artist} - {$song->title}.");
                $this->pause($delayMilliseconds);

                continue;
            }

            if ($duration === null) {
                $unmatched++;
                $this->line("No {$service} duration found for {$song->artist} - {$song->title}.");
            } else {
                if (! $dryRun) {
                    $song->update([
                        'duration' => $duration,
                        'source' => $service,
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

    private function pause(int $delayMilliseconds): void
    {
        if ($delayMilliseconds > 0) {
            usleep($delayMilliseconds * 1000);
        }
    }
}
