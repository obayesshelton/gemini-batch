<?php

namespace ObayesShelton\GeminiBatch\Commands;

use Illuminate\Console\Command;
use ObayesShelton\GeminiBatch\Enums\BatchState;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;

class BatchCheckCommand extends Command
{
    protected $signature = 'gemini-batch:check';

    protected $description = 'Dashboard overview of all Gemini batch activity';

    public function handle(): int
    {
        $this->activeBatches();
        $this->recentlyCompleted();
        $this->pendingRequestsSummary();

        return self::SUCCESS;
    }

    protected function activeBatches(): void
    {
        $this->info('Active Batches');
        $this->newLine();

        $activeBatches = GeminiBatch::query()
            ->whereIn(GeminiBatch::COLUMN_STATE, [
                BatchState::Pending,
                BatchState::Submitted,
                BatchState::Running,
            ])
            ->latest()
            ->get();

        if ($activeBatches->isEmpty()) {
            $this->line('No active batches.');
            $this->newLine();

            return;
        }

        $this->table(
            ['ID', 'Name', 'State', 'Total', 'Completed', 'Failed', 'Submitted At'],
            $activeBatches->map(fn (GeminiBatch $batch): array => [
                $batch->getAttribute(GeminiBatch::COLUMN_ID),
                $batch->getAttribute(GeminiBatch::COLUMN_DISPLAY_NAME) ?? '-',
                $batch->getAttribute(GeminiBatch::COLUMN_STATE)->value,
                $batch->getAttribute(GeminiBatch::COLUMN_TOTAL_REQUESTS),
                $batch->getAttribute(GeminiBatch::COLUMN_COMPLETED_REQUESTS),
                $batch->getAttribute(GeminiBatch::COLUMN_FAILED_REQUESTS),
                $batch->getAttribute(GeminiBatch::COLUMN_SUBMITTED_AT)?->toDateTimeString() ?? '-',
            ])->all(),
        );

        $this->newLine();
    }

    protected function recentlyCompleted(): void
    {
        $this->info('Recently Completed (last 24 hours)');
        $this->newLine();

        $recentBatches = GeminiBatch::query()
            ->whereIn(GeminiBatch::COLUMN_STATE, [
                BatchState::Completed,
                BatchState::Failed,
            ])
            ->where(GeminiBatch::COLUMN_COMPLETED_AT, '>=', now()->subDay())
            ->latest(GeminiBatch::COLUMN_COMPLETED_AT)
            ->get();

        if ($recentBatches->isEmpty()) {
            $this->line('No batches completed in the last 24 hours.');
            $this->newLine();

            return;
        }

        $this->table(
            ['ID', 'Name', 'State', 'Total', 'Completed', 'Failed', 'Duration'],
            $recentBatches->map(fn (GeminiBatch $batch): array => [
                $batch->getAttribute(GeminiBatch::COLUMN_ID),
                $batch->getAttribute(GeminiBatch::COLUMN_DISPLAY_NAME) ?? '-',
                $batch->getAttribute(GeminiBatch::COLUMN_STATE)->value,
                $batch->getAttribute(GeminiBatch::COLUMN_TOTAL_REQUESTS),
                $batch->getAttribute(GeminiBatch::COLUMN_COMPLETED_REQUESTS),
                $batch->getAttribute(GeminiBatch::COLUMN_FAILED_REQUESTS),
                $this->formatDuration($batch),
            ])->all(),
        );

        $this->newLine();
    }

    protected function pendingRequestsSummary(): void
    {
        $activeBatches = GeminiBatch::query()
            ->whereIn(GeminiBatch::COLUMN_STATE, [
                BatchState::Pending,
                BatchState::Submitted,
                BatchState::Running,
            ])
            ->latest()
            ->get();

        if ($activeBatches->isEmpty()) {
            return;
        }

        $this->info('Pending Requests Summary');
        $this->newLine();

        foreach ($activeBatches as $batch) {
            $batchId = $batch->getAttribute(GeminiBatch::COLUMN_ID);
            $displayName = $batch->getAttribute(GeminiBatch::COLUMN_DISPLAY_NAME) ?? 'unnamed';
            $state = $batch->getAttribute(GeminiBatch::COLUMN_STATE)->value;

            $counts = GeminiBatchRequest::query()
                ->where(GeminiBatchRequest::COLUMN_GEMINI_BATCH_ID, $batchId)
                ->selectRaw('state, count(*) as count')
                ->groupBy(GeminiBatchRequest::COLUMN_STATE)
                ->pluck('count', GeminiBatchRequest::COLUMN_STATE);

            $pending = $counts->get(BatchState::Pending->value, 0);
            $completed = $counts->get(BatchState::Completed->value, 0);
            $failed = $counts->get(BatchState::Failed->value, 0);
            $total = $counts->sum();

            $this->line("  Batch #{$batchId} \"{$displayName}\" ({$state})");
            $this->line("    Pending:   {$pending}");
            $this->line("    Completed: {$completed}");
            $this->line("    Failed:    {$failed}");
            $this->line("    Total:     {$total}");
            $this->newLine();
        }
    }

    protected function formatDuration(GeminiBatch $batch): string
    {
        $submittedAt = $batch->getAttribute(GeminiBatch::COLUMN_SUBMITTED_AT);
        $completedAt = $batch->getAttribute(GeminiBatch::COLUMN_COMPLETED_AT);

        if (! $submittedAt || ! $completedAt) {
            return '-';
        }

        $seconds = $completedAt->diffInSeconds($submittedAt);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }
}
