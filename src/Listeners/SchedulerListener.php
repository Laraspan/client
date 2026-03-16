<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Console\Events\ScheduledTaskFinished;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Transport\TransportInterface;

class SchedulerListener
{
    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handle(ScheduledTaskFinished $event): void
    {
        $this->buffer->push([
            'type' => 'scheduler',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'command' => $event->task->command ?? $event->task->description,
                'duration_ms' => round($event->runtime * 1000, 2),
                'exit_code' => $event->task->exitCode,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);

        $events = $this->buffer->flush();

        if (! empty($events)) {
            $this->transport->send($events);
        }
    }
}
