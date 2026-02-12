<?php

namespace ObayesShelton\GeminiBatch\Serializers;

use Illuminate\Support\Arr;
use ObayesShelton\GeminiBatch\Contracts\PayloadSerializer;
use ObayesShelton\GeminiBatch\Exceptions\BatchException;

class AgentSerializer implements PayloadSerializer
{
    /**
     * Serialize a Laravel AI SDK Agent into a Gemini API payload.
     *
     * Extracts the agent's instructions, model, and schema, then builds
     * the Gemini payload using the same path as PrismGateway.
     *
     * @param  array{agent: object, prompt: string}  $request
     */
    public function serialize(mixed $request): array
    {
        if (! is_array($request) || ! isset($request['agent'], $request['prompt'])) {
            throw new BatchException('AgentSerializer expects an array with "agent" and "prompt" keys.');
        }

        $agent = $request['agent'];
        $prompt = $request['prompt'];

        $systemInstruction = null;
        if (method_exists($agent, 'instructions')) {
            $instructions = $agent->instructions();
            if ($instructions) {
                $systemInstruction = [
                    'parts' => [['text' => $instructions]],
                ];
            }
        }

        $contents = [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ];

        $generationConfig = [];

        if (method_exists($agent, 'schema') && $agent->schema()) {
            $schemaMapClass = \EchoLabs\Prism\Providers\Gemini\Maps\SchemaMap::class;
            if (class_exists($schemaMapClass)) {
                $generationConfig['response_mime_type'] = 'application/json';
                $generationConfig['response_schema'] = (new $schemaMapClass($agent->schema()))->toArray();
            }
        }

        $tools = [];
        if (method_exists($agent, 'tools') && $agent->tools()) {
            $toolMapClass = \EchoLabs\Prism\Providers\Gemini\Maps\ToolMap::class;
            if (class_exists($toolMapClass)) {
                $tools = [['function_declarations' => $toolMapClass::map($agent->tools())]];
            }
        }

        return Arr::whereNotNull([
            'system_instruction' => $systemInstruction,
            'contents' => $contents,
            'generationConfig' => $generationConfig !== [] ? $generationConfig : null,
            'tools' => $tools !== [] ? $tools : null,
        ]);
    }
}
