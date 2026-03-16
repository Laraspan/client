<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use LaraSpan\Client\EventBuffer;
use SplObjectStorage;

class MailListener
{
    protected SplObjectStorage $pendingMessages;

    public function __construct(protected EventBuffer $buffer)
    {
        $this->pendingMessages = new SplObjectStorage;
    }

    public function handleSending(MessageSending $event): void
    {
        $this->pendingMessages[$event->message] = microtime(true);
    }

    public function handleSent(MessageSent $event): void
    {
        $startTime = null;

        if ($this->pendingMessages->contains($event->message)) {
            $startTime = $this->pendingMessages[$event->message];
            $this->pendingMessages->detach($event->message);
        }

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
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
