<?php

namespace App\Console\Commands;

use App\Jobs\FetchTopGamesJob;
use Illuminate\Console\Command;

class RefreshTopTwenty extends Command
{
    protected $signature = 'top:refresh {--dispatch-only : Only dispatch the pipeline without local output}';

    protected $description = 'Refresh the Top 20 catalog entries and queue downstream enrichment jobs.';

    public function handle(): int
    {
        FetchTopGamesJob::dispatch();

        if (! $this->option('dispatch-only')) {
            $this->info('Top 20 refresh pipeline dispatched.');
        }

        return self::SUCCESS;
    }
}
