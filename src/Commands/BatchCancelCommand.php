<?php

namespace ObayesShelton\GeminiBatch\Commands;

use Illuminate\Console\Command;
use ObayesShelton\GeminiBatch\GeminiBatchManager;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

class BatchCancelCommand extends Command
{
    protected $signature = 'gemini-batch:cancel {id : The batch ID}';

    protected $description = 'Cancel a running Gemini batch job';

    public function handle(GeminiBatchManager $manager): int
    {
        $batch = GeminiBatch::find($this->argument('id'));

        if (! $batch) {
            $this->error('Batch not found.');

            return self::FAILURE;
        }

        if ($batch->isTerminal()) {
            $this->warn("Batch is already in terminal state: {$batch->{GeminiBatch::COLUMN_STATE}->value}");

            return self::FAILURE;
        }

        $manager->cancel($batch);

        $this->info("Batch #{$batch->id} has been cancelled.");

        return self::SUCCESS;
    }
}
