<p align="center"><strong>Gemini Batch</strong></p>

<p align="center">
    <a href="https://packagist.org/packages/obayesshelton/gemini-batch"><img src="https://img.shields.io/packagist/v/obayesshelton/gemini-batch.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/obayesshelton/gemini-batch"><img src="https://img.shields.io/packagist/dt/obayesshelton/gemini-batch.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square" alt="License"></a>
</p>

------

**Queue-driven batch processing for the Google Gemini API in Laravel.** Send hundreds of AI requests as a single batch job at 50% cost — with optional [PrismPHP](https://github.com/echolabsdev/prism) and [Laravel AI SDK](https://github.com/laravel/ai) integration.

```php
use ObayesShelton\GeminiBatch\Facades\GeminiBatch;

$batch = GeminiBatch::create('gemini-2.0-flash')
    ->name('entity-enrichment')
    ->onEachResult(EnrichmentHandler::class)
    ->then(NotifyAdmin::class);

foreach ($entities as $entity) {
    $batch->addTextRequest(
        request: Prism::text()
            ->using(Provider::Gemini, 'gemini-2.0-flash')
            ->withPrompt("Profile {$entity->name}"),
        key: "entity-{$entity->id}",
        meta: ['entity_id' => $entity->id],
    );
}

$batch->dispatch(); // Queue handles submit → poll → process
```

## Key Features

- **50% cost reduction** via the [Gemini Batch API](https://ai.google.dev/gemini-api/docs/batch-api)
- **Queue-driven pipeline** — submit, poll with exponential backoff, process results
- **Fluent API** — create, add requests, dispatch
- **PrismPHP integration** — `addTextRequest()` and `addStructuredRequest()` (optional)
- **Laravel AI SDK integration** — `addAgentRequest()` for Agent classes (optional)
- **Per-request callbacks** and batch completion handlers
- **Auto-detection** of inline vs file mode based on payload size
- **Artisan commands** — `gemini-batch:list`, `status`, `cancel`, `prune`
- **Token tracking** — prompt, completion, and thought tokens per request

## Installation

```bash
composer require obayesshelton/gemini-batch
php artisan vendor:publish --tag=gemini-batch-migrations
php artisan migrate
```

Add your Gemini API key to `.env` — same key as PrismPHP, no extra credentials:

```env
GEMINI_API_KEY=your-api-key
```

## Documentation

- **[Getting Started](#getting-started)** — Installation and your first batch
- **[Adding Requests](#adding-requests)** — Raw payloads, Prism, or Laravel AI SDK Agents
- **[Result Handlers](#result-handlers)** — Processing results with per-request callbacks
- **[Configuration](#configuration)** — Polling intervals, queues, input modes
- **[Artisan Commands](#artisan-commands)** — Monitor and manage batches from the CLI

### Getting Started

The package works standalone or with PrismPHP. Without Prism, use raw Gemini payloads:

```php
GeminiBatch::create('gemini-2.0-flash')
    ->addRawRequest(
        request: ['contents' => [['role' => 'user', 'parts' => [['text' => 'Hello']]]]],
        key: 'greeting',
    )
    ->dispatch();
```

### Adding Requests

Three integration layers — use whichever fits your stack:

| Method | Requires | Use Case |
|--------|----------|----------|
| `addRawRequest()` | Nothing | Direct Gemini API payloads |
| `addTextRequest()` | `echolabsdev/prism` | Prism text generation |
| `addStructuredRequest()` | `echolabsdev/prism` | Prism structured JSON output |
| `addAgentRequest()` | `laravel/ai` | Laravel AI SDK Agent classes |

### Result Handlers

Implement `ResultHandler` to process each result as it arrives:

```php
use ObayesShelton\GeminiBatch\Contracts\ResultHandler;

class EnrichmentHandler implements ResultHandler
{
    public function __invoke(GeminiBatchRequest $request, BatchResult $result): void
    {
        Entity::find($request->meta['entity_id'])
            ->update(['description' => $result->text()]);
    }
}
```

### Configuration

Publish the config with `php artisan vendor:publish --tag=gemini-batch-config`. Key options:

| Option | Default | Description |
|--------|---------|-------------|
| `polling.interval` | `30` | Seconds between status polls |
| `polling.max_interval` | `120` | Max backoff cap |
| `queue` | `default` | Queue for batch jobs |
| `input_mode` | `auto` | `auto`, `inline`, or `file` |

### Artisan Commands

```bash
php artisan gemini-batch:list              # List all batches
php artisan gemini-batch:status {id}       # Detailed batch status
php artisan gemini-batch:cancel {id}       # Cancel a running batch
php artisan gemini-batch:prune             # Clean up old batches
```

## Contributing

Contributions are welcome! Please submit a pull request.

## Security

If you discover a security vulnerability, please email oliver@obayesshelton.com.

## License

MIT License. See [LICENSE](LICENSE) for details.
