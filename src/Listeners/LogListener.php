<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Log\Events\MessageLogged;
use LaraSpan\Client\EventBuffer;
use Throwable;

class LogListener
{
    public function __construct(protected EventBuffer $buffer) {}

    protected function resolveSource(): string
    {
        if (! app()->runningInConsole()) {
            return 'request';
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';

        if (in_array($command, ['queue:work', 'queue:listen', 'horizon:work'])) {
            return 'job';
        }

        if ($command === 'schedule:run' || $command === 'schedule:work') {
            return 'scheduler';
        }

        return 'command';
    }

    public function handle(MessageLogged $event): void
    {
        // Skip exception-level events with Throwable context — ExceptionListener handles those
        if (in_array($event->level, ['error', 'critical', 'emergency'])
            && ($event->context['exception'] ?? null) instanceof Throwable) {
            return;
        }

        $context = $event->context;
        unset($context['exception']); // Remove exception objects from context

        $this->buffer->push([
            'type' => 'log',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'level' => $event->level,
                'channel' => $event->channel ?? null,
                'source' => $this->resolveSource(),
                'message' => mb_substr($event->message, 0, 2000),
                'context' => array_slice($context, 0, 20),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
