<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use LaraSpan\Client\EventBuffer;
use SplObjectStorage;

class HttpClientListener
{
    protected SplObjectStorage $pendingRequests;

    public function __construct(protected EventBuffer $buffer)
    {
        $this->pendingRequests = new SplObjectStorage;
    }

    public function handleSending(RequestSending $event): void
    {
        $this->pendingRequests[$event->request] = microtime(true);
    }

    public function handleResponse(ResponseReceived $event): void
    {
        $startTime = null;

        if ($this->pendingRequests->offsetExists($event->request)) {
            $startTime = $this->pendingRequests[$event->request];
            $this->pendingRequests->offsetUnset($event->request);
        }

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
        $uri = $event->request->url();
        $parsed = parse_url($uri);

        $this->buffer->push([
            'type' => 'http_client',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'method' => $event->request->method(),
                'url' => $uri,
                'host' => $parsed['host'] ?? 'unknown',
                'status_code' => $event->response->status(),
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'is_slow' => $durationMs !== null && $durationMs >= 1000,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }

    public function handleFailed(ConnectionFailed $event): void
    {
        $startTime = null;

        if ($this->pendingRequests->offsetExists($event->request)) {
            $startTime = $this->pendingRequests[$event->request];
            $this->pendingRequests->offsetUnset($event->request);
        }

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
        $uri = $event->request->url();
        $parsed = parse_url($uri);

        $this->buffer->push([
            'type' => 'http_client',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'method' => $event->request->method(),
                'url' => $uri,
                'host' => $parsed['host'] ?? 'unknown',
                'status_code' => 0,
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'is_failed' => true,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
