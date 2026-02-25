<?php

namespace ObayesShelton\GeminiBatch\Serializers;

use Illuminate\Support\Arr;
use ObayesShelton\GeminiBatch\Contracts\PayloadSerializer;
use ObayesShelton\GeminiBatch\Exceptions\BatchException;

class PrismTextSerializer implements PayloadSerializer
{
    /**
     * Serialize a Prism text PendingRequest into a Gemini API payload.
     *
     * Uses Prism's own MessageMap and ToolMap to ensure identical payloads.
     *
     * @param  \Prism\Prism\Text\PendingRequest  $request
     */
    public function serialize(mixed $request): array
    {
        if (! class_exists(\Prism\Prism\Text\PendingRequest::class)) {
            throw new BatchException('PrismPHP is required for text serialization. Install echolabsdev/prism.');
        }

        $resolved = $request->toRequest();
        $messageMapClass = \Prism\Prism\Providers\Gemini\Maps\MessageMap::class;

        $messageMap = (new $messageMapClass($resolved->messages(), $resolved->systemPrompts()))();

        $providerOptions = $resolved->providerOptions();

        $thinkingConfig = Arr::whereNotNull([
            'thinkingBudget' => $providerOptions['thinkingBudget'] ?? null,
        ]);

        $generationConfig = Arr::whereNotNull([
            'temperature' => $resolved->temperature(),
            'topP' => $resolved->topP(),
            'maxOutputTokens' => $resolved->maxTokens(),
            'thinkingConfig' => $thinkingConfig !== [] ? $thinkingConfig : null,
        ]);

        $tools = $this->resolveTools($resolved, $providerOptions);

        return Arr::whereNotNull([
            ...$messageMap,
            'generationConfig' => $generationConfig !== [] ? $generationConfig : null,
            'tools' => $tools !== [] ? $tools : null,
            'safetySettings' => $providerOptions['safetySettings'] ?? null,
        ]);
    }

    protected function resolveTools(mixed $resolved, array $providerOptions): array
    {
        if (! empty($providerOptions['searchGrounding'])) {
            return [['google_search' => (object) []]];
        }

        $providerTools = [];
        if (method_exists($resolved, 'providerTools')) {
            foreach ($resolved->providerTools() as $tool) {
                $providerTools[] = [$tool->name => (object) []];
            }
        }

        if ($providerTools !== []) {
            return $providerTools;
        }

        if ($resolved->tools() !== []) {
            $toolMapClass = \Prism\Prism\Providers\Gemini\Maps\ToolMap::class;

            return [['function_declarations' => $toolMapClass::map($resolved->tools())]];
        }

        return [];
    }
}
