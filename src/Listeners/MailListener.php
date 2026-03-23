<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\ExecutionState;

class MailListener
{
    public function __construct(protected EventBuffer $buffer, protected ExecutionState $state) {}

    public function handleSending(MessageSending $event): void
    {
        try {
            // Skip mail events that originated from a notification to prevent double-counting
            if (isset($event->data['__laravel_notification'])) {
                return;
            }

            $this->state->trackPendingMail($this->messageKey($event->message), microtime(true));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSent(MessageSent $event): void
    {
        try {
            // Skip mail events that originated from a notification to prevent double-counting
            if (isset($event->data['__laravel_notification'])) {
                return;
            }

            $key = $this->messageKey($event->message);
            $startTime = $this->state->popPendingMail($key);

            $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
            $message = $event->message;

            // Capture the mailable class name from event data or message headers
            $mailableClass = $event->data['__laravel_mailable'] ?? null;
            if ($mailableClass === null && method_exists($message, 'getHeaders')) {
                $header = $message->getHeaders()->get('X-Laravel-Mailable');
                $mailableClass = $header?->getBodyAsString() ?? null;
            }

            $this->buffer->push([
                'type' => 'mail',
                'occurred_at' => now()->toIso8601String(),
                'payload' => [
                    'subject' => $message->getSubject(),
                    'to' => array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getTo() ?? []),
                    'from' => array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getFrom() ?? []),
                    'driver' => $event->data['mailer'] ?? null,
                    'mailable_class' => $mailableClass,
                    'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function messageKey(mixed $message): string
    {
        $to = array_map(fn ($addr) => method_exists($addr, 'getAddress') ? $addr->getAddress() : (string) $addr, $message->getTo() ?? []);

        return $message->getSubject().':'.implode(',', $to);
    }
}
