<?php

namespace ObayesShelton\GeminiBatch;

use Illuminate\Support\ServiceProvider;
use ObayesShelton\GeminiBatch\Api\FileUploader;
use ObayesShelton\GeminiBatch\Api\GeminiApiClient;
use ObayesShelton\GeminiBatch\Commands\BatchCancelCommand;
use ObayesShelton\GeminiBatch\Commands\BatchListCommand;
use ObayesShelton\GeminiBatch\Commands\BatchPruneCommand;
use ObayesShelton\GeminiBatch\Commands\BatchStatusCommand;

class BatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gemini-batch.php', 'gemini-batch');

        $this->app->singleton(GeminiApiClient::class, function () {
            return new GeminiApiClient(
                config('gemini-batch.gemini.url'),
                config('gemini-batch.gemini.api_key'),
            );
        });

        $this->app->singleton(FileUploader::class, function ($app) {
            return new FileUploader($app->make(GeminiApiClient::class));
        });

        $this->app->singleton(GeminiBatchManager::class, function ($app) {
            return new GeminiBatchManager($app->make(GeminiApiClient::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gemini-batch.php' => config_path('gemini-batch.php'),
            ], 'gemini-batch-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'gemini-batch-migrations');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                BatchListCommand::class,
                BatchStatusCommand::class,
                BatchCancelCommand::class,
                BatchPruneCommand::class,
            ]);
        }
    }
}
