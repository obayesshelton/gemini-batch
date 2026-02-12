<?php

namespace ObayesShelton\GeminiBatch\Serializers;

use ObayesShelton\GeminiBatch\Contracts\PayloadSerializer;

class RawRequestSerializer implements PayloadSerializer
{
    /**
     * Passthrough — the request is already a Gemini-compatible payload.
     *
     * @param  array  $request  Raw Gemini API payload
     */
    public function serialize(mixed $request): array
    {
        return $request;
    }
}
