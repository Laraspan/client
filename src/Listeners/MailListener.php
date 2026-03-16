<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use LaraSpan\Client\EventBuffer;

class MailListener
{
    protected ?float $startTime = null;

    public function __construct(protected EventBuffer $buffer) {}

    public function handleSending(MessageSending $event): void
    {
        $this->startTime = microtime(true);
    }

    public function handleSent(MessageSent $event): void
    {
        $durationMs = $this->startTime ? (microtime(true) - $this->startTime) * 1000 : null;
        $this->startTime = null;

        $message = $event->message;

        $this->buffer->push([
            'type' => 'mail',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'subject' => $message->getSubject(),
                'to' => array_keys($message->getTo() ?? []),
                'from' => array_keys($message->getFrom() ?? []),
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
