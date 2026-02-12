<?php

namespace ObayesShelton\GeminiBatch\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ObayesShelton\GeminiBatch\DTOs\BatchResult;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

class BatchRequestCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly GeminiBatchRequest $request,
        public readonly BatchResult $result,
    ) {}
}
