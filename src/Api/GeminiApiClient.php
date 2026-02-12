<?php

namespace ObayesShelton\GeminiBatch\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use ObayesShelton\GeminiBatch\Exceptions\BatchException;
use ObayesShelton\GeminiBatch\Exceptions\BatchSubmissionFailed;

class GeminiApiClient
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('gemini-batch.gemini.url'), '/');
        $this->apiKey = $apiKey ?? config('gemini-batch.gemini.api_key');
    }

    /**
     * Create a batch with inline requests.
     *
     * @param  array<int, array{request: array, metadata: array{key: string}}>  $requests
     */
    public function createInlineBatch(string $model, array $requests, ?string $displayName = null): array
    {
        $payload = [
            'batch' => array_filter([
                'display_name' => $displayName,
                'input_config' => [
                    'requests' => [
                        'requests' => $requests,
                    ],
                ],
            ]),
        ];

        $response = $this->request()
            ->post("{$this->baseUrl}/models/{$model}:batchGenerateContent", $payload);

        if ($response->failed()) {
            throw BatchSubmissionFailed::fromApiResponse($response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Create a batch with a previously uploaded file.
     */
    public function createFileBatch(string $model, string $fileUri, ?string $displayName = null): array
    {
        $payload = [
            'batch' => array_filter([
                'display_name' => $displayName,
                'input_config' => [
                    'file_name' => $fileUri,
                ],
            ]),
        ];

        $response = $this->request()
            ->post("{$this->baseUrl}/models/{$model}:batchGenerateContent", $payload);

        if ($response->failed()) {
            throw BatchSubmissionFailed::fromApiResponse($response->status(), $response->body());
        }

        return $response->json();
    }

    /**
     * Get the current status of a batch.
     */
    public function getBatch(string $batchName): array
    {
        $response = $this->request()
            ->get("{$this->baseUrl}/{$batchName}");

        if ($response->failed()) {
            throw new BatchException("Failed to get batch status for {$batchName}: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * List all batches.
     */
    public function listBatches(?int $pageSize = null, ?string $pageToken = null): array
    {
        $response = $this->request()
            ->get("{$this->baseUrl}/batches", array_filter([
                'pageSize' => $pageSize,
                'pageToken' => $pageToken,
            ]));

        if ($response->failed()) {
            throw new BatchException("Failed to list batches: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Cancel a running batch.
     */
    public function cancelBatch(string $batchName): array
    {
        $response = $this->request()
            ->post("{$this->baseUrl}/{$batchName}:cancel");

        if ($response->failed()) {
            throw new BatchException("Failed to cancel batch {$batchName}: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Delete a batch.
     */
    public function deleteBatch(string $batchName): void
    {
        $response = $this->request()
            ->post("{$this->baseUrl}/{$batchName}:delete");

        if ($response->failed()) {
            throw new BatchException("Failed to delete batch {$batchName}: {$response->body()}");
        }
    }

    /**
     * Download batch results from the response file.
     */
    public function downloadResults(string $fileUri): string
    {
        $downloadUrl = str_replace('/v1beta/', '/download/v1beta/', $this->baseUrl);

        $response = $this->request()
            ->get("{$downloadUrl}/{$fileUri}:download", [
                'alt' => 'media',
            ]);

        if ($response->failed()) {
            throw new BatchException("Failed to download batch results for {$fileUri}: {$response->body()}");
        }

        return $response->body();
    }

    /**
     * Get inline results from a completed batch response.
     *
     * @return array<int, array{response?: array, error?: array}>
     */
    public function getInlineResults(array $batchResponse): array
    {
        return $batchResponse['response']['inlinedResponses']['inlinedResponses']
            ?? $batchResponse['response']['inlinedResponses']
            ?? $batchResponse['output']['inlinedResponses']
            ?? [];
    }

    /**
     * Upload a file to the Gemini File API.
     */
    public function uploadFile(string $content, string $displayName, string $mimeType = 'application/jsonl'): array
    {
        $response = $this->request()
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'raw',
                'X-Goog-Upload-Command' => 'upload, finalize',
                'Content-Type' => $mimeType,
            ])
            ->withBody($content, $mimeType)
            ->post("{$this->baseUrl}/files", [
                'file' => [
                    'display_name' => $displayName,
                ],
            ]);

        if ($response->failed()) {
            throw new BatchException("Failed to upload file: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Upload a file using the media upload endpoint.
     */
    public function uploadMediaFile(string $content, string $displayName, string $mimeType = 'application/jsonl'): string
    {
        $uploadUrl = str_replace('/v1beta', '/upload/v1beta/files', $this->baseUrl);

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
            'X-Goog-Upload-Protocol' => 'raw',
            'X-Goog-Upload-Command' => 'upload, finalize',
            'Content-Type' => $mimeType,
        ])
            ->withBody($content, $mimeType)
            ->post($uploadUrl);

        if ($response->failed()) {
            throw new BatchException("Failed to upload media file: {$response->body()}");
        }

        $data = $response->json();

        return $data['file']['name'] ?? $data['name'] ?? '';
    }

    protected function request(): PendingRequest
    {
        return Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
        ])
            ->acceptJson()
            ->timeout(120);
    }
}
