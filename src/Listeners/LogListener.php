<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Log\Events\MessageLogged;
use LaraSpan\Client\EventBuffer;
use Throwable;

class LogListener
{
    public function __construct(protected EventBuffer $buffer) {}

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
                'message' => mb_substr($event->message, 0, 2000),
                'context' => array_slice($context, 0, 20),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
