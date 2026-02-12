<?php

namespace ObayesShelton\GeminiBatch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ObayesShelton\GeminiBatch\Api\GeminiApiClient;
use ObayesShelton\GeminiBatch\Enums\BatchState;
use ObayesShelton\GeminiBatch\Events\BatchFailed;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

class PollBatchStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $batchId,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(GeminiApiClient $client): void
    {
        $batch = GeminiBatch::find($this->batchId);

        if (! $batch || $batch->isTerminal()) {
            return;
        }

        $apiBatchName = $batch->{GeminiBatch::COLUMN_API_BATCH_NAME};

        if (! $apiBatchName) {
            Log::warning('Gemini Batch has no API batch name, cannot poll.', ['batch_id' => $this->batchId]);

            return;
        }

        try {
            $response = $client->getBatch($apiBatchName);
        } catch (\Throwable $e) {
            Log::warning('Gemini Batch poll failed, will retry.', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            $this->requeuePoll($batch);

            return;
        }

        $apiState = $response['state'] ?? 'JOB_STATE_FAILED';
        $state = BatchState::fromGeminiState($apiState);

        if ($state === BatchState::Running && $batch->{GeminiBatch::COLUMN_STATE} !== BatchState::Running) {
            $batch->markRunning();
        }

        if ($state->isTerminal()) {
            $this->handleTerminalState($batch, $state, $response);

            return;
        }

        $this->requeuePoll($batch);
    }

    protected function handleTerminalState(GeminiBatch $batch, BatchState $state, array $response): void
    {
        match ($state) {
            BatchState::Completed => $this->handleCompleted($batch, $response),
            BatchState::Failed => $this->handleFailed($batch, $response),
            BatchState::Cancelled => $batch->markCancelled(),
            BatchState::Expired => $batch->markFailed('Batch expired after 48 hours.'),
            default => $batch->markFailed("Unexpected terminal state: {$state->value}"),
        };
    }

    protected function handleCompleted(GeminiBatch $batch, array $response): void
    {
        ProcessBatchResultsJob::dispatch($batch->id, $response)
            ->onQueue($batch->{GeminiBatch::COLUMN_QUEUE} ?? config('gemini-batch.queue'))
            ->onConnection($batch->{GeminiBatch::COLUMN_CONNECTION} ?? config('gemini-batch.connection'));
    }

    protected function handleFailed(GeminiBatch $batch, array $response): void
    {
        $error = $response['error']['message'] ?? json_encode($response['error'] ?? 'Unknown error');
        $batch->markFailed($error);

        BatchFailed::dispatch($batch);
    }

    protected function requeuePoll(GeminiBatch $batch): void
    {
        $timeout = config('gemini-batch.polling.timeout', 86400);
        $submittedAt = $batch->{GeminiBatch::COLUMN_SUBMITTED_AT};

        if ($submittedAt && $submittedAt->diffInSeconds(now()) > $timeout) {
            $batch->markFailed('Polling timeout exceeded.');
            BatchFailed::dispatch($batch);

            return;
        }

        $delay = $this->calculateDelay();

        self::dispatch($this->batchId, $this->pollCount + 1)
            ->onQueue($batch->{GeminiBatch::COLUMN_QUEUE} ?? config('gemini-batch.queue'))
            ->onConnection($batch->{GeminiBatch::COLUMN_CONNECTION} ?? config('gemini-batch.connection'))
            ->delay(now()->addSeconds($delay));
    }

    protected function calculateDelay(): int
    {
        $baseInterval = config('gemini-batch.polling.interval', 30);
        $maxInterval = config('gemini-batch.polling.max_interval', 120);

        $delay = $baseInterval * (int) pow(1.5, min($this->pollCount, 10));

        return min($delay, $maxInterval);
    }
}
