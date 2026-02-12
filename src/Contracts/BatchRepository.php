<?php

namespace ObayesShelton\GeminiBatch\Contracts;

use Illuminate\Support\Collection;
use ObayesShelton\GeminiBatch\Enums\BatchState;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;

interface BatchRepository
{
    public function create(array $attributes): GeminiBatch;

    public function find(int $id): ?GeminiBatch;

    public function findByApiBatchName(string $name): ?GeminiBatch;

    public function updateState(GeminiBatch $batch, BatchState $state): void;

    public function getActive(): Collection;

    public function prune(int $days): int;
}
