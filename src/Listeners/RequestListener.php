<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\ExecutionState;
use LaraSpan\Client\Support\HeaderRedactor;
use LaraSpan\Client\Support\PerformanceFingerprinter;
use LaraSpan\Client\Support\UserProvider;

class RequestListener
{
    public function __construct(protected EventBuffer $buffer, protected ExecutionState $state) {}

    public function handle(RequestHandled $event): void
    {
        try {
            $durationMs = (microtime(true) - $this->buffer->getStartTime()) * 1000;

            $slowThreshold = config('laraspan.thresholds.slow_request_ms', 1000);
            $nPlusOneThreshold = config('laraspan.thresholds.n_plus_one_threshold', 5);

            $responseSize = null;
            try {
                $responseSize = strlen($event->response->getContent());
            } catch (\Throwable) {
                // Streaming responses may not support getContent()
            }

            $payload = [
                'route' => $event->request->route()?->uri(),
                'route_name' => $event->request->route()?->getName(),
                'route_action' => $event->request->route()?->getActionName(),
                'uri' => $event->request->getRequestUri(),
                'method' => $event->request->method(),
                'status_code' => $event->response->getStatusCode(),
                'duration_ms' => round($durationMs, 2),
                'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'query_count' => $this->buffer->getQueryCount(),
                'request_id' => $this->buffer->getRequestId(),
                'user_id' => ($user = app(UserProvider::class)->resolve()) ? $user['id'] : null,
                'user_name' => $user['name'] ?? null,
                'user_email' => $user['email'] ?? null,
                'is_slow' => $durationMs >= $slowThreshold,
                'has_n_plus_one' => $this->buffer->hasNPlusOne($nPlusOneThreshold),
                'server' => gethostname(),
                'request_size' => strlen($event->request->getContent()),
                'response_size' => $responseSize,
                'user_ip' => $event->request->ip(),
            ];

            $lifecycle = $this->state->getLifecyclePhases();
            if ($lifecycle !== null) {
                $payload['lifecycle'] = $lifecycle;
            }

            if (config('laraspan.capture.headers', false)) {
                $payload['headers'] = $this->captureHeaders($event->request);
            }

            if (config('laraspan.capture.payload', false) && $event->response->getStatusCode() >= 500) {
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
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, string> */
    protected function captureHeaders(Request $request): array
    {
        $redactor = new HeaderRedactor(config('laraspan.redact_headers', []));

        return $redactor->redact($request->headers->all());
    }
}
