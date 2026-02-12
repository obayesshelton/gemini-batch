<?php

namespace ObayesShelton\GeminiBatch\Facades;

use Illuminate\Support\Facades\Facade;
use ObayesShelton\GeminiBatch\GeminiBatchManager;

/**
 * @method static \ObayesShelton\GeminiBatch\PendingBatch create(?string $model = null)
 * @method static \ObayesShelton\GeminiBatch\Models\GeminiBatch|null find(int $id)
 * @method static \ObayesShelton\GeminiBatch\Models\GeminiBatch|null findByApiName(string $name)
 * @method static \Illuminate\Support\Collection active()
 * @method static void cancel(\ObayesShelton\GeminiBatch\Models\GeminiBatch $batch)
 * @method static int prune(?int $days = null)
 * @method static \ObayesShelton\GeminiBatch\Api\GeminiApiClient apiClient()
 *
 * @see \ObayesShelton\GeminiBatch\GeminiBatchManager
 */
class GeminiBatch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeminiBatchManager::class;
    }
}
