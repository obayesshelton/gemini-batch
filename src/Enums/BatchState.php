<?php

namespace ObayesShelton\GeminiBatch\Enums;

enum BatchState: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Map Gemini API state strings to our enum.
     */
    public static function fromGeminiState(string $state): self
    {
        return match ($state) {
            'JOB_STATE_PENDING' => self::Submitted,
            'JOB_STATE_RUNNING' => self::Running,
            'JOB_STATE_SUCCEEDED' => self::Completed,
            'JOB_STATE_FAILED' => self::Failed,
            'JOB_STATE_CANCELLED' => self::Cancelled,
            'JOB_STATE_EXPIRED' => self::Expired,
            default => self::Failed,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Expired]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Submitted, self::Running]);
    }
}
