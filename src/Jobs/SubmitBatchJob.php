<?php

namespace ObayesShelton\GeminiBatch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ObayesShelton\GeminiBatch\Api\FileUploader;
use ObayesShelton\GeminiBatch\Api\GeminiApiClient;
use ObayesShelton\GeminiBatch\Enums\BatchInputMode;
use ObayesShelton\GeminiBatch\Enums\BatchState;
use ObayesShelton\GeminiBatch\Events\BatchFailed;
use ObayesShelton\GeminiBatch\Events\BatchSubmitted;
use ObayesShelton\GeminiBatch\Exceptions\BatchSubmissionFailed;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

class SubmitBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $batchId,
    ) {}

    public function handle(GeminiApiClient $client, FileUploader $fileUploader): void
    {
        $batch = GeminiBatch::findOrFail($this->batchId);

        if ($batch->{GeminiBatch::COLUMN_STATE} !== BatchState::Pending) {
            return;
        }

        $requests = $batch->requests()
            ->get()
            ->map(fn (GeminiBatchRequest $r): array => [
                'key' => $r->{GeminiBatchRequest::COLUMN_KEY},
                'payload' => $r->{GeminiBatchRequest::COLUMN_REQUEST_PAYLOAD},
            ])
            ->all();

        if ($requests === []) {
            $batch->markFailed('No requests in batch.');
            BatchFailed::dispatch($batch);

            return;
        }

        try {
            $inputMode = $this->determineInputMode($batch, $requests, $fileUploader);
            $response = $this->submitBatch($client, $fileUploader, $batch, $requests, $inputMode);

            $apiBatchName = $response['name'] ?? '';

            $batch->update([
                GeminiBatch::COLUMN_INPUT_MODE => $inputMode,
            ]);
            $batch->markSubmitted($apiBatchName);

            BatchSubmitted::dispatch($batch);

            PollBatchStatusJob::dispatch($batch->id)
                ->onQueue($batch->{GeminiBatch::COLUMN_QUEUE} ?? config('gemini-batch.queue'))
                ->onConnection($batch->{GeminiBatch::COLUMN_CONNECTION} ?? config('gemini-batch.connection'))
                ->delay(now()->addSeconds(config('gemini-batch.polling.interval', 30)));

        } catch (BatchSubmissionFailed $e) {
            Log::error('Gemini Batch submission failed', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            $batch->markFailed($e->getMessage());
            BatchFailed::dispatch($batch);

            throw $e;
        }
    }

    protected function determineInputMode(GeminiBatch $batch, array $requests, FileUploader $fileUploader): BatchInputMode
    {
        $configMode = config('gemini-batch.input_mode', 'auto');

        if ($configMode === 'inline') {
            return BatchInputMode::Inline;
        }

        if ($configMode === 'file') {
            return BatchInputMode::File;
        }

        $threshold = config('gemini-batch.inline_threshold', 15 * 1024 * 1024);
        $estimatedSize = $fileUploader->estimatePayloadSize($requests);

        return $estimatedSize > $threshold ? BatchInputMode::File : BatchInputMode::Inline;
    }

    protected function submitBatch(GeminiApiClient $client, FileUploader $fileUploader, GeminiBatch $batch, array $requests, BatchInputMode $inputMode): array
    {
        $model = $batch->{GeminiBatch::COLUMN_MODEL};
        $displayName = $batch->{GeminiBatch::COLUMN_DISPLAY_NAME};

        if ($inputMode === BatchInputMode::File) {
            $fileUri = $fileUploader->uploadRequests($requests, $displayName ?? "batch-{$batch->id}");

            return $client->createFileBatch($model, $fileUri, $displayName);
        }

        $inlineRequests = collect($requests)->map(fn (array $r): array => [
            'request' => $r['payload'],
            'metadata' => ['key' => $r['key']],
        ])->all();

        return $client->createInlineBatch($model, $inlineRequests, $displayName);
    }
}
