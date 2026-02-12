<?php

namespace ObayesShelton\GeminiBatch\Commands;

use Illuminate\Console\Command;
use ObayesShelton\GeminiBatch\GeminiBatchManager;

class BatchPruneCommand extends Command
{
    protected $signature = 'gemini-batch:prune
                            {--days= : Days to keep (default from config)}';

    protected $description = 'Prune old completed/failed Gemini batch jobs';

    public function handle(GeminiBatchManager $manager): int
    {
        $days = $this->option('days') ? (int) $this->option('days') : null;

        $count = $manager->prune($days);

        $this->info("Pruned {$count} batch(es).");

        return self::SUCCESS;
    }
}
