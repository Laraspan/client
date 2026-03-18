<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\LaraSpanMiddleware;
use LaraSpan\Client\Support\PerformanceFingerprinter;

class RequestListener
{
    /** @var string[] Headers to always redact */
    protected array $sensitiveHeaders = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    public function __construct(protected EventBuffer $buffer) {}

    public function handle(RequestHandled $event): void
    {
        $durationMs = (microtime(true) - $this->buffer->getStartTime()) * 1000;

        $slowThreshold = config('laraspan.thresholds.slow_request_ms', 1000);
        $nPlusOneThreshold = config('laraspan.thresholds.n_plus_one_threshold', 5);

        $payload = [
            'route' => $event->request->route()?->uri(),
            'uri' => $event->request->getRequestUri(),
            'method' => $event->request->method(),
            'status_code' => $event->response->getStatusCode(),
            'duration_ms' => round($durationMs, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'query_count' => $this->buffer->getQueryCount(),
            'request_id' => $this->buffer->getRequestId(),
            'user_id' => $event->request->user()?->getAuthIdentifier(),
            'is_slow' => $durationMs >= $slowThreshold,
            'has_n_plus_one' => $this->buffer->hasNPlusOne($nPlusOneThreshold),
            'server' => gethostname(),
            'request_size' => strlen($event->request->getContent()),
            'user_ip' => $event->request->ip(),
        ];

        $lifecycle = LaraSpanMiddleware::getLifecyclePhases();
        if ($lifecycle !== null) {
            $payload['lifecycle'] = $lifecycle;
        }

        if (config('laraspan.capture.headers', false)) {
            $payload['headers'] = $this->captureHeaders($event->request);
        }

        if (config('laraspan.capture.payload', false)) {
            $payload['request_payload'] = $event->request->all();
        }

        $isSlow = $payload['is_slow'];
        $route = $payload['route'] ?? $payload['uri'] ?? null;

        $this->buffer->push([
            'type' => 'request',
            'occurred_at' => now()->toIso8601String(),
            'fingerprint' => $isSlow && $route ? PerformanceFingerprinter::request($route) : null,
            'payload' => $payload,
        ]);
    }

    /** @return array<string, string> */
    protected function captureHeaders(Request $request): array
    {
        $headers = [];
        $redactHeaders = array_merge(
            $this->sensitiveHeaders,
            array_map('strtolower', config('laraspan.redact_headers', [])),
        );

        foreach ($request->headers->all() as $key => $values) {
            if (in_array(strtolower($key), $redactHeaders, true)) {
                $headers[$key] = '[REDACTED]';
            } else {
                $headers[$key] = implode(', ', $values);
            }
        }

        return $headers;
    }
}
