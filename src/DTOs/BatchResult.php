<?php

namespace ObayesShelton\GeminiBatch\DTOs;

readonly class BatchResult
{
    public function __construct(
        public string $key,
        public bool $successful,
        public ?array $response,
        public ?string $error,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public ?int $thoughtTokens,
    ) {}

    public function text(): ?string
    {
        if (! $this->successful || ! $this->response) {
            return null;
        }

        $candidates = $this->response['candidates'] ?? [];

        if ($candidates === []) {
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? [];

        return collect($parts)
            ->filter(fn (array $part): bool => isset($part['text']) && ! ($part['thought'] ?? false))
            ->pluck('text')
            ->implode('');
    }

    public function structuredOutput(): ?array
    {
        $text = $this->text();

        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function thinking(): ?string
    {
        if (! $this->successful || ! $this->response) {
            return null;
        }

        $candidates = $this->response['candidates'] ?? [];

        if ($candidates === []) {
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? [];

        return collect($parts)
            ->filter(fn (array $part): bool => isset($part['text']) && ($part['thought'] ?? false))
            ->pluck('text')
            ->implode('') ?: null;
    }

    public static function fromGeminiResponse(string $key, array $data): self
    {
        $error = $data['error'] ?? null;
        $response = $data['response'] ?? null;

        $usageMetadata = $response['usageMetadata'] ?? [];

        return new self(
            key: $key,
            successful: $error === null && $response !== null,
            response: $response,
            error: $error ? json_encode($error) : null,
            promptTokens: $usageMetadata['promptTokenCount'] ?? null,
            completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
            thoughtTokens: $usageMetadata['thoughtsTokenCount'] ?? null,
        );
    }
}
