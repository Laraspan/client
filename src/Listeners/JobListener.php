<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Support\ExceptionFingerprinter;
use LaraSpan\Client\Support\LazyValue;
use LaraSpan\Client\Transport\TransportInterface;

class JobListener
{
    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handleProcessing(JobProcessing $event): void
    {
        try {
            if ($this->isLaraSpanJob($event->job->resolveName())) {
                $this->buffer->pause();

                return;
            }

            $this->buffer->reset();

            $payload = json_decode($event->job->getRawBody(), true);
            $traceId = $payload['laraspan']['trace_id'] ?? null;
            if ($traceId) {
                $this->buffer->setContext(['parent_request_id' => $traceId]);
            }

            $userId = $payload['laraspan']['user_id'] ?? null;
            if ($userId) {
                $this->buffer->getExecutionState()->setUserId($userId);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleProcessed(JobProcessed $event): void
    {
        try {
            if ($this->isLaraSpanJob($event->job->resolveName())) {
                return;
            }

            $startTime = $this->buffer->getStartTime();
            $jobClass = $event->job->resolveName();

            $this->buffer->push([
                'type' => 'job',
                'occurred_at' => now()->toIso8601String(),
                'fingerprint' => sha1('job:'.$jobClass),
                'payload' => [
                    'job_class' => $jobClass,
                    'queue' => $event->job->getQueue(),
                    'connection_name' => $event->connectionName,
                    'job_id' => $event->job->getJobId(),
                    'max_tries' => $event->job->payload()['maxTries'] ?? null,
                    'attempt' => $event->job->attempts(),
                    'duration_ms' => new LazyValue(fn () => round((microtime(true) - $startTime) * 1000, 2)),
                    'memory_mb' => new LazyValue(fn () => round(memory_get_peak_usage(true) / 1024 / 1024, 2)),
                    'status' => 'processed',
                    'is_failed' => false,
                    'is_slow' => new LazyValue(fn () => (microtime(true) - $startTime) * 1000 >= config('laraspan.thresholds.slow_job_ms', 5000)),
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);

            $this->flushBuffer();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleFailed(JobFailed $event): void
    {
        try {
            if ($this->isLaraSpanJob($event->job->resolveName())) {
                return;
            }

            $startTime = $this->buffer->getStartTime();
            $jobClass = $event->job->resolveName();

            $payload = [
                'job_class' => $jobClass,
                'queue' => $event->job->getQueue(),
                'connection_name' => $event->connectionName,
                'job_id' => $event->job->getJobId(),
                'max_tries' => $event->job->payload()['maxTries'] ?? null,
                'attempt' => $event->job->attempts(),
                'duration_ms' => new LazyValue(fn () => round((microtime(true) - $startTime) * 1000, 2)),
                'memory_mb' => new LazyValue(fn () => round(memory_get_peak_usage(true) / 1024 / 1024, 2)),
                'status' => 'failed',
                'is_failed' => true,
                'is_slow' => new LazyValue(fn () => (microtime(true) - $startTime) * 1000 >= config('laraspan.thresholds.slow_job_ms', 5000)),
                'request_id' => $this->buffer->getRequestId(),
            ];

            if ($event->exception) {
                $payload['exception'] = [
                    'class' => get_class($event->exception),
                    'message' => $event->exception->getMessage(),
                    'file' => $event->exception->getFile(),
                    'line' => $event->exception->getLine(),
                    'fingerprint' => ExceptionFingerprinter::fingerprint($event->exception),
                ];
            }

            $this->buffer->push([
                'type' => 'job',
                'occurred_at' => now()->toIso8601String(),
                'fingerprint' => sha1('job:'.$jobClass),
                'payload' => $payload,
            ]);

            $this->flushBuffer();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function isLaraSpanJob(string $jobClass): bool
    {
        return $jobClass === FlushEventsJob::class;
    }

    protected function flushBuffer(): void
    {
        $events = $this->buffer->flush();

        if (! empty($events)) {
            $this->transport->send($events);
        }
    }
}
