<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable LaraSpan Monitoring
    |--------------------------------------------------------------------------
    */

    'enabled' => env('LARASPAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application Token
    |--------------------------------------------------------------------------
    |
    | The API token used to authenticate with the LaraSpan server.
    |
    */

    'token' => env('LARASPAN_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Server URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your LaraSpan server. All API paths (/api/ingest,
    | /api/deploy) are derived from this automatically.
    |
    */

    'url' => env('LARASPAN_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | How events are sent to the server. Options: "queue" (Redis buffer +
    | queue flush, recommended) or "inline" (direct HTTP, for apps without Redis).
    |
    */

    'transport' => env('LARASPAN_TRANSPORT', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | Monitors
    |--------------------------------------------------------------------------
    |
    | Toggle individual monitors on or off.
    |
    */

    'monitors' => [
        'exceptions' => env('LARASPAN_MONITOR_EXCEPTIONS', true),
        'requests' => env('LARASPAN_MONITOR_REQUESTS', true),
        'queries' => env('LARASPAN_MONITOR_QUERIES', true),
        'jobs' => env('LARASPAN_MONITOR_JOBS', true),
        'scheduler' => env('LARASPAN_MONITOR_SCHEDULER', true),
        'cache' => env('LARASPAN_MONITOR_CACHE', true),
        'mail' => env('LARASPAN_MONITOR_MAIL', true),
        'notification' => env('LARASPAN_MONITOR_NOTIFICATION', true),
        'http_client' => env('LARASPAN_MONITOR_HTTP_CLIENT', true),
        'command' => env('LARASPAN_MONITOR_COMMAND', true),
        'log' => env('LARASPAN_MONITOR_LOG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Buffer Settings
    |--------------------------------------------------------------------------
    */

    'buffer' => [
        'flush_threshold' => env('LARASPAN_FLUSH_THRESHOLD', 100),
        'max_batch_size' => env('LARASPAN_MAX_BATCH_SIZE', 500),
        'max_events_per_request' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'slow_request_ms' => env('LARASPAN_SLOW_REQUEST_MS', 1000),
        'slow_query_ms' => env('LARASPAN_SLOW_QUERY_MS', 100),
        'n_plus_one_threshold' => env('LARASPAN_N_PLUS_ONE_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Rate from 0.0 to 1.0 for each event type. Exceptions always bypass
    | sampling (rate is effectively 1.0).
    |
    */

    'sampling' => [
        'request' => env('LARASPAN_SAMPLE_REQUEST', 1.0),
        'query' => env('LARASPAN_SAMPLE_QUERY', 1.0),
        'job' => env('LARASPAN_SAMPLE_JOB', 1.0),
        'scheduler' => env('LARASPAN_SAMPLE_SCHEDULER', 1.0),
        'cache' => env('LARASPAN_SAMPLE_CACHE', 1.0),
        'mail' => env('LARASPAN_SAMPLE_MAIL', 1.0),
        'notification' => env('LARASPAN_SAMPLE_NOTIFICATION', 1.0),
        'http_client' => env('LARASPAN_SAMPLE_HTTP_CLIENT', 1.0),
        'command' => env('LARASPAN_SAMPLE_COMMAND', 1.0),
        'log' => env('LARASPAN_SAMPLE_LOG', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes that should not be reported.
    |
    */

    'ignore_vendor_events' => env('LARASPAN_IGNORE_VENDOR_EVENTS', true),

    'ignore_exceptions' => [
        NotFoundHttpException::class,
        AuthenticationException::class,
        ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted Keys
    |--------------------------------------------------------------------------
    |
    | Payload keys matching these patterns will have their values replaced
    | with [REDACTED]. Matching is case-insensitive.
    |
    */

    'redact' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Settings
    |--------------------------------------------------------------------------
    */

    'queries' => [
        'capture_bindings' => env('LARASPAN_CAPTURE_BINDINGS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Capture
    |--------------------------------------------------------------------------
    |
    | Opt-in capture of request headers and payload. Sensitive headers
    | (Authorization, Cookie) are auto-redacted. Additional headers to
    | redact can be added to the redact_headers array.
    |
    */

    'capture' => [
        'headers' => env('LARASPAN_CAPTURE_HEADERS', false),
        'payload' => env('LARASPAN_CAPTURE_PAYLOAD', false),
        'source_code' => env('LARASPAN_CAPTURE_SOURCE_CODE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted Headers
    |--------------------------------------------------------------------------
    |
    | Additional header names to redact beyond the built-in sensitive
    | headers (Authorization, Cookie, Set-Cookie, X-CSRF-Token, X-XSRF-Token).
    |
    */

    'redact_headers' => explode(',', env('LARASPAN_REDACT_HEADERS', '')),

];
