<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Notifications\Events\NotificationSent;
use LaraSpan\Client\EventBuffer;

class NotificationListener
{
    public function __construct(protected EventBuffer $buffer) {}

    public function handle(NotificationSent $event): void
    {
        $this->buffer->push([
            'type' => 'notification',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'notification_class' => get_class($event->notification),
                'channel' => $event->channel,
                'notifiable_type' => get_class($event->notifiable),
                'notifiable_id' => $event->notifiable->getKey(),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
