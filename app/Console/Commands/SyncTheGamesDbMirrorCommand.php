<?php

namespace App\Console\Commands;

use App\Jobs\TgdbFullSyncJob;
use App\Jobs\TgdbIncrementalUpdateJob;
use App\Jobs\TgdbSweepShardJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function dispatch;

class SyncTheGamesDbMirrorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tgdb:sync
        {--mode=incremental : Sync mode (full, incremental, sweep)}
        {--queue : Dispatch to the queue instead of running inline}
        {--source=manual : Source label recorded in logs/telemetry}
        {--shard= : Sweep mode only: shard index to process}
        {--total-shards= : Sweep mode only: total shard count}
        {--window-days= : Sweep mode only: window size in days}
        {--daily-budget= : Sweep mode only: maximum calls to use}
        {--chunk-size= : Sweep mode only: chunk size per request}';

    /**
     * @var string
     */
    protected $description = 'Run TheGamesDB mirror sync jobs manually.';

    public function handle(): int
    {
        $mode = Str::lower((string) $this->option('mode'));
        $enqueue = (bool) $this->option('queue');
        $source = (string) $this->option('source');

        $job = match ($mode) {
            'full' => new TgdbFullSyncJob([
                'source' => $source,
            ]),
            'sweep' => new TgdbSweepShardJob(array_filter([
                'source' => $source,
                'shard' => $this->option('shard'),
                'total_shards' => $this->option('total-shards'),
                'window_days' => $this->option('window-days'),
                'daily_budget' => $this->option('daily-budget'),
                'chunk_size' => $this->option('chunk-size'),
            ], static fn ($value) => $value !== null && $value !== '')),
            default => new TgdbIncrementalUpdateJob([
                'source' => $source,
            ]),
        };

        $modeLabel = match ($mode) {
            'full' => 'full sync',
            'sweep' => 'sweep shard',
            default => 'incremental sync',
        };

        if ($enqueue) {
            dispatch($job);

            $this->components->info(sprintf('Queued TheGamesDB %s job on queue [%s].', $modeLabel, $job->queue ?? 'default'));

            return self::SUCCESS;
        }

        if (method_exists($job, 'handle')) {
            /** @var callable $handler */
            $handler = [$job, 'handle'];

            app()->call($handler);

            $this->components->info(sprintf('Ran TheGamesDB %s job synchronously.', $modeLabel));

            return self::SUCCESS;
        }

        $this->components->error('Unable to execute the requested sync job.');

        return self::FAILURE;
    }
}
