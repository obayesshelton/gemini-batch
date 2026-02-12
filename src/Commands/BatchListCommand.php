<?php

namespace ObayesShelton\GeminiBatch\Commands;

use Illuminate\Console\Command;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

class BatchListCommand extends Command
{
    protected $signature = 'gemini-batch:list
                            {--state= : Filter by state (pending, submitted, running, completed, failed)}
                            {--limit=20 : Number of batches to show}';

    protected $description = 'List Gemini batch jobs';

    public function handle(): int
    {
        $query = GeminiBatch::query()->latest();

        $state = $this->option('state');
        if ($state) {
            $query->where(GeminiBatch::COLUMN_STATE, $state);
        }

        $batches = $query->limit((int) $this->option('limit'))->get();

        if ($batches->isEmpty()) {
            $this->info('No batches found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Model', 'State', 'Requests', 'Completed', 'Failed', 'Created'],
            $batches->map(fn (GeminiBatch $batch): array => [
                $batch->{GeminiBatch::COLUMN_ID},
                $batch->{GeminiBatch::COLUMN_DISPLAY_NAME} ?? '-',
                $batch->{GeminiBatch::COLUMN_MODEL},
                $batch->{GeminiBatch::COLUMN_STATE}->value,
                $batch->{GeminiBatch::COLUMN_TOTAL_REQUESTS},
                $batch->{GeminiBatch::COLUMN_COMPLETED_REQUESTS},
                $batch->{GeminiBatch::COLUMN_FAILED_REQUESTS},
                $batch->{GeminiBatch::COLUMN_CREATED_AT}->diffForHumans(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
