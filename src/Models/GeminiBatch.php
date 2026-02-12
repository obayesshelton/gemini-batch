<?php

namespace ObayesShelton\GeminiBatch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ObayesShelton\GeminiBatch\Enums\BatchInputMode;
use ObayesShelton\GeminiBatch\Enums\BatchState;

class GeminiBatch extends Model
{
    public const TABLE = 'gemini_batches';

    public const COLUMN_ID = 'id';

    public const COLUMN_API_BATCH_NAME = 'api_batch_name';

    public const COLUMN_MODEL = 'model';

    public const COLUMN_DISPLAY_NAME = 'display_name';

    public const COLUMN_STATE = 'state';

    public const COLUMN_INPUT_MODE = 'input_mode';

    public const COLUMN_TOTAL_REQUESTS = 'total_requests';

    public const COLUMN_COMPLETED_REQUESTS = 'completed_requests';

    public const COLUMN_FAILED_REQUESTS = 'failed_requests';

    public const COLUMN_ON_COMPLETED_HANDLER = 'on_completed_handler';

    public const COLUMN_ON_EACH_RESULT_HANDLER = 'on_each_result_handler';

    public const COLUMN_METADATA = 'metadata';

    public const COLUMN_ERROR_MESSAGE = 'error_message';

    public const COLUMN_QUEUE = 'queue';

    public const COLUMN_CONNECTION = 'connection';

    public const COLUMN_SUBMITTED_AT = 'submitted_at';

    public const COLUMN_COMPLETED_AT = 'completed_at';

    public const COLUMN_CREATED_AT = 'created_at';

    public const COLUMN_UPDATED_AT = 'updated_at';

    protected $guarded = [];

    public function getTable(): string
    {
        return config('gemini-batch.tables.batches', self::TABLE);
    }

    protected function casts(): array
    {
        return [
            self::COLUMN_STATE => BatchState::class,
            self::COLUMN_INPUT_MODE => BatchInputMode::class,
            self::COLUMN_METADATA => 'array',
            self::COLUMN_TOTAL_REQUESTS => 'integer',
            self::COLUMN_COMPLETED_REQUESTS => 'integer',
            self::COLUMN_FAILED_REQUESTS => 'integer',
            self::COLUMN_SUBMITTED_AT => 'datetime',
            self::COLUMN_COMPLETED_AT => 'datetime',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(GeminiBatchRequest::class, GeminiBatchRequest::COLUMN_GEMINI_BATCH_ID);
    }

    public function isTerminal(): bool
    {
        return $this->{self::COLUMN_STATE}->isTerminal();
    }

    public function isActive(): bool
    {
        return $this->{self::COLUMN_STATE}->isActive();
    }

    public function markSubmitted(string $apiBatchName): void
    {
        $this->update([
            self::COLUMN_API_BATCH_NAME => $apiBatchName,
            self::COLUMN_STATE => BatchState::Submitted,
            self::COLUMN_SUBMITTED_AT => now(),
        ]);
    }

    public function markRunning(): void
    {
        $this->update([
            self::COLUMN_STATE => BatchState::Running,
        ]);
    }

    public function markCompleted(int $completedRequests, int $failedRequests): void
    {
        $this->update([
            self::COLUMN_STATE => BatchState::Completed,
            self::COLUMN_COMPLETED_REQUESTS => $completedRequests,
            self::COLUMN_FAILED_REQUESTS => $failedRequests,
            self::COLUMN_COMPLETED_AT => now(),
        ]);
    }

    public function markFailed(?string $errorMessage = null): void
    {
        $this->update([
            self::COLUMN_STATE => BatchState::Failed,
            self::COLUMN_ERROR_MESSAGE => $errorMessage,
            self::COLUMN_COMPLETED_AT => now(),
        ]);
    }

    public function markCancelled(): void
    {
        $this->update([
            self::COLUMN_STATE => BatchState::Cancelled,
            self::COLUMN_COMPLETED_AT => now(),
        ]);
    }
}
