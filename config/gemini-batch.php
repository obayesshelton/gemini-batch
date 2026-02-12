<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Configuration
    |--------------------------------------------------------------------------
    |
    | Shares the same GEMINI_API_KEY as PrismPHP — no extra credentials needed.
    |
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'url' => env('GEMINI_BATCH_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_BATCH_MODEL', 'gemini-2.0-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how frequently we check batch status. Uses exponential backoff
    | starting at `interval` seconds, capped at `max_interval`.
    |
    */

    'polling' => [
        'interval' => (int) env('GEMINI_BATCH_POLL_INTERVAL', 30),
        'max_interval' => (int) env('GEMINI_BATCH_POLL_MAX_INTERVAL', 120),
        'timeout' => (int) env('GEMINI_BATCH_POLL_TIMEOUT', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | The default queue and connection for batch processing jobs.
    |
    */

    'queue' => env('GEMINI_BATCH_QUEUE', 'default'),
    'connection' => env('GEMINI_BATCH_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Input Mode
    |--------------------------------------------------------------------------
    |
    | How requests are sent to the Gemini Batch API:
    | - 'auto': Detects inline vs file based on payload size
    | - 'inline': Always inline (must be under 20MB)
    | - 'file': Always upload JSONL file via File API
    |
    */

    'input_mode' => env('GEMINI_BATCH_INPUT_MODE', 'auto'),
    'inline_threshold' => 15 * 1024 * 1024, // 15MB (buffer below 20MB API limit)

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'store_response_payloads' => true,
    'prune_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names if needed.
    |
    */

    'tables' => [
        'batches' => 'gemini_batches',
        'requests' => 'gemini_batch_requests',
    ],

];
