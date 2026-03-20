<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Support\ExceptionFingerprinter;
use LaraSpan\Client\Transport\TransportInterface;

class JobListener
{
    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handleProcessing(JobProcessing $event): void
    {
        if ($this->isLaraSpanJob($event->job->resolveName())) {
            $this->buffer->pause();

            return;
        }

        $this->buffer->reset();
    }

    public function handleProcessed(JobProcessed $event): void
    {
        if ($this->isLaraSpanJob($event->job->resolveName())) {
            return;
        }

        $durationMs = (microtime(true) - $this->buffer->getStartTime()) * 1000;
        $slowThreshold = config('laraspan.thresholds.slow_job_ms', 5000);
        $isSlow = $durationMs >= $slowThreshold;
        $jobClass = $event->job->resolveName();

        $this->buffer->push([
            'type' => 'job',
            'occurred_at' => now()->toIso8601String(),
            'fingerprint' => sha1('job:'.$jobClass),
            'payload' => [
                'job_class' => $jobClass,
                'queue' => $event->job->getQueue(),
                'attempt' => $event->job->attempts(),
                'duration_ms' => round($durationMs, 2),
                'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'status' => 'processed',
                'is_failed' => false,
                'is_slow' => $isSlow,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);

        $this->flushBuffer();
    }

    public function handleFailed(JobFailed $event): void
    {
        if ($this->isLaraSpanJob($event->job->resolveName())) {
            return;
        }

        $durationMs = (microtime(true) - $this->buffer->getStartTime()) * 1000;
        $slowThreshold = config('laraspan.thresholds.slow_job_ms', 5000);
        $isSlow = $durationMs >= $slowThreshold;
        $jobClass = $event->job->resolveName();

        $payload = [
            'job_class' => $jobClass,
            'queue' => $event->job->getQueue(),
            'attempt' => $event->job->attempts(),
            'duration_ms' => round($durationMs, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'status' => 'failed',
            'is_failed' => true,
            'is_slow' => $isSlow,
            'request_id' => $this->buffer->getRequestId(),
        ];

        if ($event->exception) {
            $payload['exception'] = [
                'class' => get_class($event->exception),
                'message' => $event->exception->getMessage(),
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
