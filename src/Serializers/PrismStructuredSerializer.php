<?php

namespace ObayesShelton\GeminiBatch\Serializers;

use Illuminate\Support\Arr;
use ObayesShelton\GeminiBatch\Contracts\PayloadSerializer;
use ObayesShelton\GeminiBatch\Exceptions\BatchException;

class PrismStructuredSerializer implements PayloadSerializer
{
    /**
     * Serialize a Prism structured PendingRequest into a Gemini API payload.
     *
     * Adds response_mime_type and response_schema for structured JSON output.
     *
     * @param  \EchoLabs\Prism\Prism\PendingRequest  $request
     */
    public function serialize(mixed $request): array
    {
        if (! class_exists(\EchoLabs\Prism\Prism\PendingRequest::class)) {
            throw new BatchException('PrismPHP is required for structured serialization. Install echolabsdev/prism.');
        }

        $resolved = $request->toRequest();
        $messageMapClass = \EchoLabs\Prism\Providers\Gemini\Maps\MessageMap::class;
        $schemaMapClass = \EchoLabs\Prism\Providers\Gemini\Maps\SchemaMap::class;

        $messageMap = (new $messageMapClass($resolved->messages(), $resolved->systemPrompts()))();

        $providerOptions = $resolved->providerOptions();

        $thinkingConfig = Arr::whereNotNull([
            'thinkingBudget' => $providerOptions['thinkingBudget'] ?? null,
        ]);

        $generationConfig = Arr::whereNotNull([
            'response_mime_type' => 'application/json',
            'response_schema' => (new $schemaMapClass($resolved->schema()))->toArray(),
            'temperature' => $resolved->temperature(),
            'topP' => $resolved->topP(),
            'maxOutputTokens' => $resolved->maxTokens(),
            'thinkingConfig' => $thinkingConfig !== [] ? $thinkingConfig : null,
        ]);

        return Arr::whereNotNull([
            ...$messageMap,
            'generationConfig' => $generationConfig,
            'safetySettings' => $providerOptions['safetySettings'] ?? null,
        ]);
    }
}
