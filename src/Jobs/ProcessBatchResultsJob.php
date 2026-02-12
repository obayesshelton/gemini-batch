<?php

namespace ObayesShelton\GeminiBatch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ObayesShelton\GeminiBatch\Api\GeminiApiClient;
use ObayesShelton\GeminiBatch\Contracts\ResultHandler;
use ObayesShelton\GeminiBatch\DTOs\BatchResult;
use ObayesShelton\GeminiBatch\Enums\BatchInputMode;
use ObayesShelton\GeminiBatch\Events\BatchCompleted;
use ObayesShelton\GeminiBatch\Events\BatchFailed;
use ObayesShelton\GeminiBatch\Events\BatchRequestCompleted;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

class ProcessBatchResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 600;

    public function __construct(
        public readonly int $batchId,
        public readonly array $apiResponse,
    ) {}

    public function handle(GeminiApiClient $client): void
    {
        $batch = GeminiBatch::findOrFail($this->batchId);

        try {
            $results = $this->fetchResults($client, $batch);
            $this->processResults($batch, $results);

            $completedCount = $batch->requests()
                ->where(GeminiBatchRequest::COLUMN_STATE, 'completed')
                ->count();
            $failedCount = $batch->requests()
                ->where(GeminiBatchRequest::COLUMN_STATE, 'failed')
                ->count();

            $batch->markCompleted($completedCount, $failedCount);

            $this->runCompletedHandler($batch);

            BatchCompleted::dispatch($batch);

        } catch (\Throwable $e) {
            Log::error('Failed to process batch results', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            $batch->markFailed("Result processing failed: {$e->getMessage()}");
            BatchFailed::dispatch($batch);

            throw $e;
        }
    }

    /**
     * @return array<int, array{key: string, response?: array, error?: array}>
     */
    protected function fetchResults(GeminiApiClient $client, GeminiBatch $batch): array
    {
        if ($batch->{GeminiBatch::COLUMN_INPUT_MODE} === BatchInputMode::File) {
            return $this->fetchFileResults($client);
        }

        return $this->fetchInlineResults($client);
    }

    protected function fetchInlineResults(GeminiApiClient $client): array
    {
        $inlinedResponses = $client->getInlineResults($this->apiResponse);

        return collect($inlinedResponses)->map(function (array $item, int $index): array {
            return [
                'key' => $item['metadata']['key'] ?? "request-{$index}",
                'response' => $item['response'] ?? null,
                'error' => $item['error'] ?? null,
            ];
        })->all();
    }

    protected function fetchFileResults(GeminiApiClient $client): array
    {
        $destFile = $this->apiResponse['response']['outputFile']
            ?? $this->apiResponse['response']['output_file']
            ?? $this->apiResponse['dest']['file_name']
            ?? $this->apiResponse['dest']['fileName']
            ?? null;

        if (! $destFile) {
            throw new \RuntimeException('No destination file found in batch response.');
        }

        $content = $client->downloadResults($destFile);

        return $this->parseJsonl($content);
    }

    /**
     * @return array<int, array{key: string, response?: array, error?: array}>
     */
    protected function parseJsonl(string $content): array
    {
        $results = [];

        foreach (explode("\n", trim($content)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if (! is_array($data)) {
                continue;
            }

            $results[] = [
                'key' => $data['key'] ?? $data['metadata']['key'] ?? '',
                'response' => $data['response'] ?? null,
                'error' => $data['error'] ?? null,
            ];
        }

        return $results;
    }

    protected function processResults(GeminiBatch $batch, array $results): void
    {
        $requestsByKey = $batch->requests()
            ->get()
            ->keyBy(GeminiBatchRequest::COLUMN_KEY);

        $eachResultHandler = $batch->{GeminiBatch::COLUMN_ON_EACH_RESULT_HANDLER};

        foreach ($results as $resultData) {
            $key = $resultData['key'];
            $batchRequest = $requestsByKey->get($key);

            if (! $batchRequest) {
                Log::warning("No matching request found for key: {$key}", ['batch_id' => $batch->id]);

                continue;
            }

            $batchResult = BatchResult::fromGeminiResponse($key, $resultData);

            if ($batchResult->successful) {
                $storePayloads = config('gemini-batch.store_response_payloads', true);

                $batchRequest->markCompleted(
                    responsePayload: $storePayloads ? ($resultData['response'] ?? []) : [],
                    responseText: $batchResult->text(),
                    structuredResponse: $batchResult->structuredOutput(),
                    promptTokens: $batchResult->promptTokens,
                    completionTokens: $batchResult->completionTokens,
                    thoughtTokens: $batchResult->thoughtTokens,
                );
            } else {
                $batchRequest->markFailed($batchResult->error);
            }

            BatchRequestCompleted::dispatch($batchRequest, $batchResult);

            if ($eachResultHandler) {
                $this->runResultHandler($eachResultHandler, $batchRequest, $batchResult);
            }
        }
    }

    protected function runResultHandler(string $handlerClass, GeminiBatchRequest $request, BatchResult $result): void
    {
        try {
            $handler = app($handlerClass);

            if ($handler instanceof ResultHandler) {
                $handler($request, $result);
            }
        } catch (\Throwable $e) {
            Log::error('Batch result handler failed', [
                'handler' => $handlerClass,
                'request_key' => $request->{GeminiBatchRequest::COLUMN_KEY},
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function runCompletedHandler(GeminiBatch $batch): void
    {
        $handler = $batch->{GeminiBatch::COLUMN_ON_COMPLETED_HANDLER};

        if (! $handler) {
            return;
        }

        try {
            $callback = app($handler);
            $callback($batch);
        } catch (\Throwable $e) {
            Log::error('Batch completed handler failed', [
                'handler' => $handler,
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
