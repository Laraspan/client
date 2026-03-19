<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use LaraSpan\Client\EventBuffer;

class NotificationListener
{
    /** @var array<string, float> */
    protected array $pendingNotifications = [];

    public function __construct(protected EventBuffer $buffer) {}

    public function handleSending(NotificationSending $event): void
    {
        $this->pendingNotifications[$this->notificationKey($event)] = microtime(true);
    }

    public function handleSent(NotificationSent $event): void
    {
        $key = $this->notificationKey($event);
        $startTime = $this->pendingNotifications[$key] ?? null;
        unset($this->pendingNotifications[$key]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
        $notificationClass = get_class($event->notification);

        $this->buffer->push([
            'type' => 'notification',
            'fingerprint' => sha1('notification:'.$notificationClass),
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'notification_class' => $notificationClass,
                'channel' => $event->channel,
                'notifiable_type' => get_class($event->notifiable),
                'notifiable_id' => $event->notifiable->getKey(),
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }

    protected function notificationKey(NotificationSending|NotificationSent $event): string
    {
        return get_class($event->notification).':'.$event->channel.':'.get_class($event->notifiable).':'.$event->notifiable->getKey();
    }
}
