<?php

namespace ObayesShelton\GeminiBatch\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

class BatchFailed
{
    use Dispatchable;

    public function __construct(
        public readonly GeminiBatch $batch,
    ) {}
}
