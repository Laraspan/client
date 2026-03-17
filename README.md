# LaraSpan Client

[![Latest Version](https://img.shields.io/packagist/v/laraspan/client.svg)](https://packagist.org/packages/laraspan/client)
[![License](https://img.shields.io/packagist/l/laraspan/client.svg)](https://packagist.org/packages/laraspan/client)

Monitoring client for Laravel applications. Collects exceptions, requests, queries, jobs, and more — sends them to your self-hosted [LaraSpan](https://github.com/Laraspan) server.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Redis (recommended) or any queue driver

## Installation

```bash
composer require laraspan/client
php artisan laraspan:install
```

This publishes `config/laraspan.php` and adds environment variables to your `.env`:

```env
LARASPAN_TOKEN=your-app-api-token
LARASPAN_URL=https://laraspan.yourdomain.com
LARASPAN_TRANSPORT=queue
```

Get your API token from the LaraSpan dashboard under **Applications → New Application**.

Verify the connection:

```bash
php artisan laraspan:test
```

## How It Works

```
Request lifecycle
  │
  ├── Middleware records start time + user
  ├── Listeners collect events in-memory (zero I/O)
  └── terminate()
        ├── Sample → Redact → Filter
        └── RPUSH to Redis (<0.5ms)
              │
              └── FlushEventsJob (queue worker)
                    └── Batch + gzip + POST → LaraSpan Server
```

- **Zero response time impact** — only a sub-millisecond Redis write on terminate
- **Efficient batching** — one HTTP call handles events from hundreds of requests
- **No extra processes** — uses your existing queue workers
- **Works everywhere** — PHP-FPM, Octane, queue workers, CLI commands

## What Gets Monitored

| Monitor | Captures |
|---------|----------|
| **Exceptions** | Class, message, stack trace, source code, fingerprint for deduplication |
| **Requests** | Route, method, status, duration, memory, query count, N+1 detection |
| **Queries** | SQL, duration, connection, slow query flagging |
| **Jobs** | Class, queue, duration, memory, status, failure details |
| **Scheduler** | Command, duration, exit code |
| **Cache** | Key, operation (hit/miss/write/forget), store |
| **Mail** | Subject, recipients, duration |
| **Notifications** | Channel, notifiable, notification class |
| **HTTP Client** | Method, URL, status, duration |
| **Commands** | Artisan command execution |
| **Logs** | Log level, message, context |

## Configuration

### Enable/Disable

```env
LARASPAN_ENABLED=false  # disable without removing the package
```

### Monitors

```php
// config/laraspan.php
'monitors' => [
    'exceptions'    => true,
    'requests'      => true,
    'queries'       => true,
    'jobs'          => true,
    'scheduler'     => true,
    'cache'         => true,
    'mail'          => true,
    'notification'  => true,
    'http_client'   => true,
    'command'       => true,
    'log'           => true,
],
```

### Transport

```env
LARASPAN_TRANSPORT=queue   # 'queue' (recommended) or 'inline'
```

- **queue** — events buffered in Redis, flushed by background job. Requires Redis + queue worker.
- **inline** — events sent via HTTP on terminate. No Redis needed, but adds 5–50ms per request.

### Buffer

```php
'buffer' => [
    'flush_threshold'        => 100,   // dispatch flush job after N events in Redis
    'max_batch_size'         => 500,   // max events per HTTP POST
    'max_events_per_request' => 5000,  // safety cap per request
],
```

### Thresholds

```php
'thresholds' => [
    'slow_request_ms'      => 1000,  // flag requests slower than 1s
    'slow_query_ms'        => 100,   // flag queries slower than 100ms
    'n_plus_one_threshold' => 5,     // flag after 5 repeated query patterns
],
```

### Sampling

Reduce event volume on high-traffic apps:

```php
'sampling' => [
    'request'     => 1.0,  // 1.0 = 100%, 0.1 = 10%
    'query'       => 1.0,
    'job'         => 1.0,
    'cache'       => 1.0,
    'mail'        => 1.0,
    // ... per-type rates
],
```

Exceptions are always captured regardless of sampling rate.

#### Per-Route Sampling

```php
use LaraSpan\Client\Middleware\Sample;

Route::post('/webhooks', WebhookController::class)
    ->middleware(Sample::rate(0.1));  // 10% sampling

Route::get('/health', HealthController::class)
    ->middleware(Sample::never());   // never sample
```

### Ignored Exceptions

```php
'ignore_exceptions' => [
    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    \Illuminate\Auth\AuthenticationException::class,
    \Illuminate\Validation\ValidationException::class,
],
```

### Redaction

Sensitive fields are replaced with `[REDACTED]` before leaving your application:

```php
'redact' => [
    'password', 'password_confirmation', 'secret', 'token',
    'api_key', 'authorization', 'credit_card', 'card_number',
    'cvv', 'ssn',
],
```

### Capture Options

```php
'capture' => [
    'headers'          => false,  // capture request/response headers
    'payload'          => false,  // capture request body
    'source_code'      => true,   // capture code context around exceptions
    'capture_bindings' => false,  // capture SQL query bindings
],
```

## Programmatic Control

```php
use LaraSpan\Client\LaraSpan;

// Temporarily pause/resume capture
LaraSpan::pause();
LaraSpan::resume();

// Execute code without capturing
LaraSpan::ignore(function () {
    // this won't be monitored
});

// Override sampling for current request
LaraSpan::sample(0.5);    // 50%
LaraSpan::dontSample();   // 0%
```

## Multi-Tenant Applications

Attach tenant context to all events:

```php
app(\LaraSpan\Client\EventBuffer::class)->setContext([
    'tenant_id'   => $tenant->id,
    'tenant_name' => $tenant->name,
]);
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `laraspan:install` | Publish config and add env variables |
| `laraspan:test` | Send a test event to verify connectivity |
| `laraspan:flush` | Manually flush buffered events |
| `laraspan:deploy --version=1.2.0` | Record a deployment (auto-detects commit & deployer) |

## Local Development

```env
LARASPAN_ENABLED=true
LARASPAN_URL=http://localhost:8000
LARASPAN_TRANSPORT=inline
```

Or disable entirely with `LARASPAN_ENABLED=false`.

## License

MIT
