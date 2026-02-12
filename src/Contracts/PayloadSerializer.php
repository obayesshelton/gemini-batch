<?php

namespace ObayesShelton\GeminiBatch\Contracts;

interface PayloadSerializer
{
    /**
     * Serialize a request into a Gemini API-compatible payload.
     *
     * @return array{contents: array, generationConfig?: array, system_instruction?: array, tools?: array}
     */
    public function serialize(mixed $request): array;
}
