<?php

namespace App\Console\Commands;

use App\Support\FeatureTourConfig;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('app:prune-feature-tour-cache
    {--store= : Cache store to prune (defaults to cache.default)}
    {--dry-run : Show what would be deleted without deleting it}')]
#[Description('Prune stale cached merged feature tour configs for stores that support key iteration')]
class PruneFeatureTourCache extends Command
{
    public function handle(): int
    {
        $storeName = (string) ($this->option('store') ?: config('cache.default'));

        if ($storeName === '') {
            $this->error('Unable to resolve cache store name.');

            return self::FAILURE;
        }

        $storeConfig = config("cache.stores.{$storeName}");

        if (! is_array($storeConfig)) {
            $this->error("Cache store [{$storeName}] is not configured.");

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $repository = Cache::store($storeName);
        $store = $repository->getStore();

        if ($store instanceof RedisStore) {
            try {
                $deletedCount = $this->pruneRedisStore($store, $dryRun);
            } catch (Throwable $exception) {
                $this->error('Failed to prune Redis cache store: '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->info($dryRun
                ? "[dry-run] Found {$deletedCount} feature tour cache key(s) in Redis store [{$storeName}]."
                : "Deleted {$deletedCount} feature tour cache key(s) from Redis store [{$storeName}].");

            return self::SUCCESS;
        }

        if ($store instanceof DatabaseStore) {
            try {
                $deletedCount = $this->pruneDatabaseStore($storeName, $store, $dryRun);
            } catch (Throwable $exception) {
                $this->error('Failed to prune database cache store: '.$exception->getMessage());

                return self::FAILURE;
            }

            $this->info($dryRun
                ? "[dry-run] Found {$deletedCount} feature tour cache row(s) in database store [{$storeName}]."
                : "Deleted {$deletedCount} feature tour cache row(s) from database store [{$storeName}].");

            return self::SUCCESS;
        }

        $driver = (string) ($storeConfig['driver'] ?? get_class($store));
        $this->warn("Cache store [{$storeName}] with driver [{$driver}] is not supported for key iteration pruning.");
        $this->line('Supported drivers: redis, database.');

        return self::INVALID;
    }

    private function pruneDatabaseStore(string $storeName, DatabaseStore $store, bool $dryRun): int
    {
        $table = (string) config("cache.stores.{$storeName}.table", 'cache');
        $keyPrefix = $store->getPrefix().FeatureTourConfig::MERGED_CONFIG_CACHE_KEY_PREFIX;

        $query = $store->getConnection()
            ->table($table)
            ->where('key', 'like', $keyPrefix.'%');

        $count = (int) (clone $query)->count();

        if ($dryRun || $count === 0) {
            return $count;
        }

        return (int) $query->delete();
    }

    private function pruneRedisStore(RedisStore $store, bool $dryRun): int
    {
        $connection = $store->connection();
        $pattern = $store->getPrefix().FeatureTourConfig::MERGED_CONFIG_CACHE_KEY_PREFIX.'*';
        $cursor = '0';
        $deleted = 0;

        do {
            $scanResult = $connection->scan($cursor, [
                'match' => $pattern,
                'count' => 1000,
            ]);

            if (! is_array($scanResult)) {
                break;
            }

            [$cursor, $keys] = $scanResult;

            if (! is_array($keys) || $keys === []) {
                continue;
            }

            $deleted += count($keys);

            if ($dryRun) {
                continue;
            }

            try {
                $connection->del($keys);
            } catch (Throwable) {
                foreach ($keys as $key) {
                    $connection->del($key);
                }
            }
        } while ((string) $cursor !== '0');

        return $deleted;
    }
}
