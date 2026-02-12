<?php

namespace ObayesShelton\GeminiBatch\Commands;

use Illuminate\Console\Command;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

class BatchStatusCommand extends Command
{
    protected $signature = 'gemini-batch:status {id : The batch ID}';

    protected $description = 'Show detailed status of a Gemini batch job';

    public function handle(): int
    {
        $batch = GeminiBatch::find($this->argument('id'));

        if (! $batch) {
            $this->error('Batch not found.');

            return self::FAILURE;
        }

        $this->info("Batch #{$batch->id}");
        $this->newLine();

        $this->table([], [
            ['Display Name', $batch->{GeminiBatch::COLUMN_DISPLAY_NAME} ?? '-'],
            ['Model', $batch->{GeminiBatch::COLUMN_MODEL}],
            ['State', $batch->{GeminiBatch::COLUMN_STATE}->value],
            ['Input Mode', $batch->{GeminiBatch::COLUMN_INPUT_MODE}?->value ?? '-'],
            ['API Batch Name', $batch->{GeminiBatch::COLUMN_API_BATCH_NAME} ?? '-'],
            ['Total Requests', $batch->{GeminiBatch::COLUMN_TOTAL_REQUESTS}],
            ['Completed', $batch->{GeminiBatch::COLUMN_COMPLETED_REQUESTS}],
            ['Failed', $batch->{GeminiBatch::COLUMN_FAILED_REQUESTS}],
            ['Queue', $batch->{GeminiBatch::COLUMN_QUEUE} ?? 'default'],
            ['Submitted At', $batch->{GeminiBatch::COLUMN_SUBMITTED_AT}?->toDateTimeString() ?? '-'],
            ['Completed At', $batch->{GeminiBatch::COLUMN_COMPLETED_AT}?->toDateTimeString() ?? '-'],
            ['Created At', $batch->{GeminiBatch::COLUMN_CREATED_AT}->toDateTimeString()],
        ]);

        if ($batch->{GeminiBatch::COLUMN_ERROR_MESSAGE}) {
            $this->newLine();
            $this->error("Error: {$batch->{GeminiBatch::COLUMN_ERROR_MESSAGE}}");
        }

        $failedRequests = $batch->requests()
            ->where(GeminiBatchRequest::COLUMN_STATE, 'failed')
            ->get();

        if ($failedRequests->isNotEmpty()) {
            $this->newLine();
            $this->warn('Failed Requests:');
            $this->table(
                ['Key', 'Error'],
                $failedRequests->map(fn (GeminiBatchRequest $r): array => [
                    $r->{GeminiBatchRequest::COLUMN_KEY},
                    str($r->{GeminiBatchRequest::COLUMN_ERROR_MESSAGE})->limit(80)->toString(),
                ])->all(),
            );
        }

        $totalTokens = $batch->requests()->sum(GeminiBatchRequest::COLUMN_PROMPT_TOKENS)
            + $batch->requests()->sum(GeminiBatchRequest::COLUMN_COMPLETION_TOKENS);

        if ($totalTokens > 0) {
            $this->newLine();
            $this->info('Token Usage:');
            $this->table([], [
                ['Prompt Tokens', number_format($batch->requests()->sum(GeminiBatchRequest::COLUMN_PROMPT_TOKENS))],
                ['Completion Tokens', number_format($batch->requests()->sum(GeminiBatchRequest::COLUMN_COMPLETION_TOKENS))],
                ['Thought Tokens', number_format($batch->requests()->sum(GeminiBatchRequest::COLUMN_THOUGHT_TOKENS))],
                ['Total', number_format($totalTokens)],
            ]);
        }

        return self::SUCCESS;
    }
}
