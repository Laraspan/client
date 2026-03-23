<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\ExecutionState;

class HttpClientListener
{
    public function __construct(protected EventBuffer $buffer, protected ExecutionState $state) {}

    public function handleSending(RequestSending $event): void
    {
        try {
            $this->state->trackPendingHttpRequest($this->requestKey($event->request), microtime(true));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleResponse(ResponseReceived $event): void
    {
        try {
            $key = $this->requestKey($event->request);
            $startTime = $this->state->popPendingHttpRequest($key);

            $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
            $uri = $this->stripUserInfo($event->request->url());
            $parsed = parse_url($uri);

            $host = $parsed['host'] ?? 'unknown';
            $path = $parsed['path'] ?? '/';
            $statusCode = $event->response->status();
            $responseSize = $event->response->header('Content-Length');

            $this->buffer->push([
                'type' => 'http_client',
                'fingerprint' => sha1('http_client:'.$host),
                'occurred_at' => now()->toIso8601String(),
                'payload' => [
                    'method' => $event->request->method(),
                    'url' => $uri,
                    'host' => $host,
                    'path' => $path,
                    'status_code' => $statusCode,
                    'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                    'response_size' => $responseSize !== null ? (int) $responseSize : null,
                    'is_slow' => $durationMs !== null && $durationMs >= config('laraspan.thresholds.slow_http_client_ms', 1000),
                    'is_error' => $statusCode >= 400,
                    'is_failed' => false,
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleFailed(ConnectionFailed $event): void
    {
        try {
            $key = $this->requestKey($event->request);
            $startTime = $this->state->popPendingHttpRequest($key);

            $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
            $uri = $this->stripUserInfo($event->request->url());
            $parsed = parse_url($uri);

            $host = $parsed['host'] ?? 'unknown';
            $path = $parsed['path'] ?? '/';
            $errorMessage = method_exists($event, 'getException')
                ? $event->getException()?->getMessage()
                : ($event->exception ?? null)?->getMessage();

            $this->buffer->push([
                'type' => 'http_client',
                'fingerprint' => sha1('http_client:'.$host),
                'occurred_at' => now()->toIso8601String(),
                'payload' => [
                    'method' => $event->request->method(),
                    'url' => $uri,
                    'host' => $host,
                    'path' => $path,
                    'status_code' => 0,
                    'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                    'response_size' => null,
                    'is_slow' => $durationMs !== null && $durationMs >= config('laraspan.thresholds.slow_http_client_ms', 1000),
                    'is_error' => true,
                    'is_failed' => true,
                    'error_message' => $errorMessage,
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function requestKey(mixed $request): string
    {
        return spl_object_id($request).':'.$request->method().' '.$request->url();
    }

    /**
     * Strip userinfo (username:password@) from a URL to avoid logging credentials.
     */
    protected function stripUserInfo(string $url): string
    {
        $parsed = parse_url($url);

        if (! isset($parsed['user'])) {
            return $url;
        }

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'].'://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        return $scheme.$host.$port.$path.$query.$fragment;
    }
}
