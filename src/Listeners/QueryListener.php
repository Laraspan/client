<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Support\SqlNormalizer;

class QueryListener
{
    public function __construct(protected EventBuffer $buffer) {}

    public function handle(QueryExecuted $event): void
    {
        $normalizedSql = SqlNormalizer::normalize($event->sql);
        $this->buffer->trackQueryPattern($normalizedSql);

        $slowThreshold = config('laraspan.thresholds.slow_query_ms', 100);

        $payload = [
            'sql' => $event->sql,
            'duration_ms' => $event->time,
            'connection' => $event->connectionName,
            'request_id' => $this->buffer->getRequestId(),
            'is_slow' => $event->time >= $slowThreshold,
            'normalized_sql' => $normalizedSql,
        ];

        if (config('laraspan.queries.capture_bindings', false)) {
            $payload['bindings'] = $event->bindings;
        }

        $this->buffer->push([
            'type' => 'query',
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ]);
    }
}
