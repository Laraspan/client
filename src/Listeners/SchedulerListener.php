<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Console\Events\ScheduledTaskFinished;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Transport\TransportInterface;

class SchedulerListener
{
    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handle(ScheduledTaskFinished $event): void
    {
        try {
            $description = $event->task->command ?? $event->task->description ?? '';

            if (str_contains($description, FlushEventsJob::class)) {
                return;
            }

            $isFailed = ($event->task->exitCode ?? 0) !== 0;

            $this->buffer->push([
                'type' => 'scheduler',
                'fingerprint' => sha1('scheduler:'.$description),
                'occurred_at' => now()->toIso8601String(),
                'payload' => [
                    'command' => $description,
                    'expression' => $event->task->expression,
                    'timezone' => $event->task->timezone instanceof \DateTimeZone
                        ? $event->task->timezone->getName()
                        : ($event->task->timezone ?? null),
                    'status' => $isFailed ? 'failed' : 'processed',
                    'duration_ms' => round($event->runtime * 1000, 2),
                    'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                    'exit_code' => $event->task->exitCode,
                    'is_failed' => $isFailed,
                    'server' => gethostname(),
                    'without_overlapping' => (bool) $event->task->withoutOverlapping,
                    'on_one_server' => (bool) $event->task->onOneServer,
                    'run_in_background' => (bool) $event->task->runInBackground,
                    'even_in_maintenance_mode' => (bool) $event->task->evenInMaintenanceMode,
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);

            $events = $this->buffer->flush();

            if (! empty($events)) {
                $this->transport->send($events);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
