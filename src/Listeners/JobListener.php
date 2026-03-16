<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Support\ExceptionFingerprinter;
use LaraSpan\Client\Transport\TransportInterface;

class JobListener
{
    protected ?float $startTime = null;

    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handleProcessing(JobProcessing $event): void
    {
        $this->buffer->reset();
        $this->startTime = microtime(true);

        $this->buffer->setContext([
            'request_id' => $this->buffer->getRequestId(),
        ]);
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $durationMs = $this->startTime ? (microtime(true) - $this->startTime) * 1000 : null;

        $this->buffer->push([
            'type' => 'job',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'job_class' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'attempt' => $event->job->attempts(),
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'status' => 'processed',
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);

        $this->flushBuffer();
    }

    public function handleFailed(JobFailed $event): void
    {
        $durationMs = $this->startTime ? (microtime(true) - $this->startTime) * 1000 : null;

        $payload = [
            'job_class' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'attempt' => $event->job->attempts(),
            'duration_ms' => $durationMs ? round($durationMs, 2) : null,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'status' => 'failed',
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
            'payload' => $payload,
        ]);

        $this->flushBuffer();
    }

    protected function flushBuffer(): void
    {
        $events = $this->buffer->flush();

        if (! empty($events)) {
            $this->transport->send($events);
        }
    }
}
