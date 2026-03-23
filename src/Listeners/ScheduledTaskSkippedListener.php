<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Console\Events\ScheduledTaskSkipped;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Transport\TransportInterface;

class ScheduledTaskSkippedListener
{
    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handle(ScheduledTaskSkipped $event): void
    {
        try {
            $description = $event->task->command ?? $event->task->description ?? '';

            if (str_contains($description, FlushEventsJob::class)) {
                return;
            }

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
                    'status' => 'skipped',
                    'duration_ms' => 0,
                    'memory_mb' => 0,
                    'exit_code' => null,
                    'is_failed' => false,
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
