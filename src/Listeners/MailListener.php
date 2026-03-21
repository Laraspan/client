<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use LaraSpan\Client\EventBuffer;

class MailListener
{
    /** @var array<string, float> */
    protected array $pendingMessages = [];

    public function __construct(protected EventBuffer $buffer) {}

    public function handleSending(MessageSending $event): void
    {
        $this->pendingMessages[$this->messageKey($event->message)] = microtime(true);
    }

    public function handleSent(MessageSent $event): void
    {
        $key = $this->messageKey($event->message);
        $startTime = $this->pendingMessages[$key] ?? null;
        unset($this->pendingMessages[$key]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
        $message = $event->message;

        $this->buffer->push([
            'type' => 'mail',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'subject' => $message->getSubject(),
                'to' => array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getTo() ?? []),
                'from' => array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getFrom() ?? []),
                'driver' => $event->data['mailer'] ?? null,
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }

    protected function messageKey(mixed $message): string
    {
        $to = array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getTo() ?? []);

        return $message->getSubject().':'.implode(',', $to);
    }
}
