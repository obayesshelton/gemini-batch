<?php

namespace ObayesShelton\GeminiBatch;

use Illuminate\Support\Collection;
use ObayesShelton\GeminiBatch\Api\GeminiApiClient;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

class GeminiBatchManager
{
    public function __construct(
        protected GeminiApiClient $client,
    ) {}

    /**
     * Start building a new batch.
     */
    public function create(?string $model = null): PendingBatch
    {
        return new PendingBatch($model ?? config('gemini-batch.gemini.model', 'gemini-2.0-flash'));
    }

    /**
     * Find a batch by ID.
     */
    public function find(int $id): ?GeminiBatch
    {
        return GeminiBatch::find($id);
    }

    /**
     * Find a batch by its Gemini API batch name.
     */
    public function findByApiName(string $name): ?GeminiBatch
    {
        return GeminiBatch::where(GeminiBatch::COLUMN_API_BATCH_NAME, $name)->first();
    }

    /**
     * Get all active (non-terminal) batches.
     */
    public function active(): Collection
    {
        return GeminiBatch::whereIn(GeminiBatch::COLUMN_STATE, ['pending', 'submitted', 'running'])->get();
    }

    /**
     * Cancel a batch via the Gemini API.
     */
    public function cancel(GeminiBatch $batch): void
    {
        $apiBatchName = $batch->{GeminiBatch::COLUMN_API_BATCH_NAME};

        if ($apiBatchName) {
            $this->client->cancelBatch($apiBatchName);
        }

        $batch->markCancelled();
    }

    /**
     * Prune completed/failed batches older than the configured number of days.
     */
    public function prune(?int $days = null): int
    {
        $days ??= config('gemini-batch.prune_after_days', 30);

        return GeminiBatch::whereIn(GeminiBatch::COLUMN_STATE, ['completed', 'failed', 'cancelled', 'expired'])
            ->where(GeminiBatch::COLUMN_COMPLETED_AT, '<', now()->subDays($days))
            ->each(function (GeminiBatch $batch) {
                $batch->requests()->delete();
                $batch->delete();
            })
            ->count();
    }

    /**
     * Get the underlying API client.
     */
    public function apiClient(): GeminiApiClient
    {
        return $this->client;
    }
}
