<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Support\PerformanceFingerprinter;
use LaraSpan\Client\Support\SqlNormalizer;

class QueryListener
{
    public function __construct(protected EventBuffer $buffer) {}

    public function handle(QueryExecuted $event): void
    {
        try {
            $normalizedSql = SqlNormalizer::normalize($event->sql);
            $this->buffer->trackQueryPattern($normalizedSql);

            $slowThreshold = config('laraspan.thresholds.slow_query_ms', 100);

            // Walk the backtrace to find the first application frame (non-vendor)
            $queryFile = null;
            $queryLine = null;
            $basePath = base_path();
            $vendorPath = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR;

            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
                if (! isset($frame['file'])) {
                    continue;
                }

                if (str_starts_with($frame['file'], $basePath) && ! str_starts_with($frame['file'], $vendorPath)) {
                    $queryFile = $frame['file'];
                    $queryLine = $frame['line'] ?? null;
                    break;
                }
            }

            $payload = [
                'sql' => $event->sql,
                'duration_ms' => round($event->time, 2),
                'connection' => $event->connectionName,
                'db_driver' => $event->connection->getDriverName(),
                'request_id' => $this->buffer->getRequestId(),
                'is_slow' => $event->time >= $slowThreshold,
                'normalized_sql' => $normalizedSql,
                'file' => $queryFile,
                'line' => $queryLine,
            ];

            if (config('laraspan.queries.capture_bindings', false)) {
                $payload['bindings'] = $event->bindings;
            }

            $isSlow = $payload['is_slow'];

            $this->buffer->push([
                'type' => 'query',
                'occurred_at' => now()->toIso8601String(),
                'fingerprint' => $isSlow ? PerformanceFingerprinter::query($event->sql) : null,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
