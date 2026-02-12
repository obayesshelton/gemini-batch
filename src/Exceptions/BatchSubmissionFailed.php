<?php

namespace ObayesShelton\GeminiBatch\Exceptions;

class BatchSubmissionFailed extends BatchException
{
    public static function fromApiResponse(int $status, string $body): self
    {
        return new self("Batch submission failed with status {$status}: {$body}");
    }

    public static function emptyBatch(): self
    {
        return new self('Cannot submit a batch with no requests.');
    }
}
