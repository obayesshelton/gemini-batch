<?php

namespace ObayesShelton\GeminiBatch;

use ObayesShelton\GeminiBatch\Contracts\ResultHandler;
use ObayesShelton\GeminiBatch\Enums\BatchState;
use ObayesShelton\GeminiBatch\Events\BatchCreated;
use ObayesShelton\GeminiBatch\Exceptions\BatchSubmissionFailed;
use ObayesShelton\GeminiBatch\Jobs\SubmitBatchJob;
use ObayesShelton\GeminiBatch\Models\GeminiBatch;
use ObayesShelton\GeminiBatch\Models\GeminiBatchRequest;
use ObayesShelton\GeminiBatch\Serializers\AgentSerializer;
use ObayesShelton\GeminiBatch\Serializers\PrismStructuredSerializer;
use ObayesShelton\GeminiBatch\Serializers\PrismTextSerializer;
use ObayesShelton\GeminiBatch\Serializers\RawRequestSerializer;

class PendingBatch
{
    protected string $model;

    protected ?string $displayName = null;

    protected ?string $queue = null;

    protected ?string $connection = null;

    /** @var class-string<ResultHandler>|null */
    protected ?string $onEachResultHandler = null;

    /** @var class-string|null */
    protected ?string $onCompletedHandler = null;

    protected array $metadata = [];

    /** @var array<int, array{key: string, payload: array, meta: array}> */
    protected array $requests = [];

    protected int $requestIndex = 0;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function name(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Register a handler that runs for each individual result.
     *
     * @param  class-string<ResultHandler>  $handler
     */
    public function onEachResult(string $handler): self
    {
        $this->onEachResultHandler = $handler;

        return $this;
    }

    /**
     * Register a handler that runs when the entire batch completes.
     *
     * @param  class-string  $handler
     */
    public function then(string $handler): self
    {
        $this->onCompletedHandler = $handler;

        return $this;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Add a raw Gemini API request (no dependencies).
     */
    public function addRawRequest(array $request, ?string $key = null, array $meta = []): self
    {
        $serializer = new RawRequestSerializer;

        return $this->addSerializedRequest($serializer->serialize($request), $key, $meta);
    }

    /**
     * Add a Prism text request (requires echolabsdev/prism).
     */
    public function addTextRequest(mixed $request, ?string $key = null, array $meta = []): self
    {
        $serializer = new PrismTextSerializer;

        return $this->addSerializedRequest($serializer->serialize($request), $key, $meta);
    }

    /**
     * Add a Prism structured request (requires echolabsdev/prism).
     */
    public function addStructuredRequest(mixed $request, ?string $key = null, array $meta = []): self
    {
        $serializer = new PrismStructuredSerializer;

        return $this->addSerializedRequest($serializer->serialize($request), $key, $meta);
    }

    /**
     * Add a Laravel AI SDK Agent request (requires laravel/ai + echolabsdev/prism).
     */
    public function addAgentRequest(object $agent, string $prompt, ?string $key = null, array $meta = []): self
    {
        $serializer = new AgentSerializer;

        return $this->addSerializedRequest(
            $serializer->serialize(['agent' => $agent, 'prompt' => $prompt]),
            $key,
            $meta,
        );
    }

    /**
     * Add an already-serialized payload.
     */
    public function addSerializedRequest(array $payload, ?string $key = null, array $meta = []): self
    {
        $key ??= 'request-'.$this->requestIndex;

        $this->requests[] = [
            'key' => $key,
            'payload' => $payload,
            'meta' => $meta,
        ];

        $this->requestIndex++;

        return $this;
    }

    /**
     * Persist the batch and dispatch the submission job.
     */
    public function dispatch(): GeminiBatch
    {
        if ($this->requests === []) {
            throw BatchSubmissionFailed::emptyBatch();
        }

        $batch = GeminiBatch::create([
            GeminiBatch::COLUMN_MODEL => $this->model,
            GeminiBatch::COLUMN_DISPLAY_NAME => $this->displayName,
            GeminiBatch::COLUMN_STATE => BatchState::Pending,
            GeminiBatch::COLUMN_TOTAL_REQUESTS => count($this->requests),
            GeminiBatch::COLUMN_ON_COMPLETED_HANDLER => $this->onCompletedHandler,
            GeminiBatch::COLUMN_ON_EACH_RESULT_HANDLER => $this->onEachResultHandler,
            GeminiBatch::COLUMN_METADATA => $this->metadata !== [] ? $this->metadata : null,
            GeminiBatch::COLUMN_QUEUE => $this->queue ?? config('gemini-batch.queue'),
            GeminiBatch::COLUMN_CONNECTION => $this->connection ?? config('gemini-batch.connection'),
        ]);

        foreach ($this->requests as $request) {
            GeminiBatchRequest::create([
                GeminiBatchRequest::COLUMN_GEMINI_BATCH_ID => $batch->id,
                GeminiBatchRequest::COLUMN_KEY => $request['key'],
                GeminiBatchRequest::COLUMN_STATE => BatchState::Pending,
                GeminiBatchRequest::COLUMN_REQUEST_PAYLOAD => $request['payload'],
                GeminiBatchRequest::COLUMN_META => $request['meta'] !== [] ? $request['meta'] : null,
            ]);
        }

        BatchCreated::dispatch($batch);

        SubmitBatchJob::dispatch($batch->id)
            ->onQueue($batch->{GeminiBatch::COLUMN_QUEUE})
            ->onConnection($batch->{GeminiBatch::COLUMN_CONNECTION});

        return $batch;
    }

    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }
}
