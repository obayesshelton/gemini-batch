<?php

namespace ObayesShelton\GeminiBatch\Api;

use ObayesShelton\GeminiBatch\Exceptions\BatchException;

class FileUploader
{
    public function __construct(
        protected GeminiApiClient $client,
    ) {}

    /**
     * Build a JSONL string from batch request payloads and upload it.
     *
     * @param  array<int, array{key: string, payload: array}>  $requests
     * @return string The file URI (e.g., "files/abc123")
     */
    public function uploadRequests(array $requests, string $displayName): string
    {
        $jsonl = $this->buildJsonl($requests);

        return $this->client->uploadMediaFile($jsonl, $displayName);
    }

    /**
     * Build a JSONL string from request payloads.
     *
     * Each line: {"key": "...", "request": {...}}
     *
     * @param  array<int, array{key: string, payload: array}>  $requests
     */
    public function buildJsonl(array $requests): string
    {
        $lines = [];

        foreach ($requests as $request) {
            $line = json_encode([
                'key' => $request['key'],
                'request' => $request['payload'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($line === false) {
                throw new BatchException("Failed to JSON-encode request with key: {$request['key']}");
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Calculate the approximate size of the JSONL payload in bytes.
     *
     * @param  array<int, array{key: string, payload: array}>  $requests
     */
    public function estimatePayloadSize(array $requests): int
    {
        $size = 0;

        foreach ($requests as $request) {
            $size += strlen(json_encode([
                'key' => $request['key'],
                'request' => $request['payload'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            $size += 1; // newline
        }

        return $size;
    }
}
