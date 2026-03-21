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
                'notifiable_id' => method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : null,
                'duration_ms' => $durationMs ? round($durationMs, 2) : null,
                'source' => get_class($event->notifiable),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }

    public function resetPending(): void
    {
        $this->pendingNotifications = [];
    }

    protected function notificationKey(NotificationSending|NotificationSent $event): string
    {
        $notifiableId = method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : spl_object_id($event->notifiable);

        return get_class($event->notification).':'.$event->channel.':'.get_class($event->notifiable).':'.$notifiableId;
    }
}
