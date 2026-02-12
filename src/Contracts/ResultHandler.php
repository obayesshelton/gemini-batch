<?php

namespace ObayesShelton\GeminiBatch\Contracts;

use ObayesShelton\GeminiBatch\DTOs\BatchResult;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

interface ResultHandler
{
    public function __invoke(GeminiBatchRequest $request, BatchResult $result): void;
}
