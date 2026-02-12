<?php

namespace ObayesShelton\GeminiBatch\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ObayesShelton\GeminiBatch\Enums\BatchState;

class GeminiBatchRequest extends Model
{
    public const TABLE = 'gemini_batch_requests';

    public const COLUMN_ID = 'id';

    public const COLUMN_GEMINI_BATCH_ID = 'gemini_batch_id';

    public const COLUMN_KEY = 'key';

    public const COLUMN_STATE = 'state';

    public const COLUMN_REQUEST_PAYLOAD = 'request_payload';

    public const COLUMN_RESPONSE_PAYLOAD = 'response_payload';

    public const COLUMN_RESPONSE_TEXT = 'response_text';

    public const COLUMN_STRUCTURED_RESPONSE = 'structured_response';

    public const COLUMN_META = 'meta';

    public const COLUMN_PROMPT_TOKENS = 'prompt_tokens';

    public const COLUMN_COMPLETION_TOKENS = 'completion_tokens';

    public const COLUMN_THOUGHT_TOKENS = 'thought_tokens';

    public const COLUMN_ERROR_MESSAGE = 'error_message';

    public const COLUMN_COMPLETED_AT = 'completed_at';

    public const COLUMN_CREATED_AT = 'created_at';

    public const COLUMN_UPDATED_AT = 'updated_at';

    protected $guarded = [];

    public function getTable(): string
    {
        return config('gemini-batch.tables.requests', self::TABLE);
    }

    protected function casts(): array
    {
        return [
            self::COLUMN_STATE => BatchState::class,
            self::COLUMN_REQUEST_PAYLOAD => 'array',
            self::COLUMN_RESPONSE_PAYLOAD => 'array',
            self::COLUMN_STRUCTURED_RESPONSE => 'array',
            self::COLUMN_META => 'array',
            self::COLUMN_PROMPT_TOKENS => 'integer',
            self::COLUMN_COMPLETION_TOKENS => 'integer',
            self::COLUMN_THOUGHT_TOKENS => 'integer',
            self::COLUMN_COMPLETED_AT => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GeminiBatch::class, self::COLUMN_GEMINI_BATCH_ID);
    }

    public function markCompleted(array $responsePayload, ?string $responseText, ?array $structuredResponse, ?int $promptTokens, ?int $completionTokens, ?int $thoughtTokens): void
    {
        $this->update([
            self::COLUMN_STATE => BatchState::Completed,
            self::COLUMN_RESPONSE_PAYLOAD => $responsePayload,
            self::COLUMN_RESPONSE_TEXT => $responseText,
            self::COLUMN_STRUCTURED_RESPONSE => $structuredResponse,
            self::COLUMN_PROMPT_TOKENS => $promptTokens,
            self::COLUMN_COMPLETION_TOKENS => $completionTokens,
            self::COLUMN_THOUGHT_TOKENS => $thoughtTokens,
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
}
