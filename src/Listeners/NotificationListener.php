<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\ExecutionState;

class NotificationListener
{
    public function __construct(protected EventBuffer $buffer, protected ExecutionState $state) {}

    public function handleSending(NotificationSending $event): void
    {
        try {
            $this->state->trackPendingNotification($this->notificationKey($event), microtime(true));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSent(NotificationSent $event): void
    {
        try {
            $key = $this->notificationKey($event);
            $startTime = $this->state->popPendingNotification($key);

            $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : null;
            $notificationClass = get_class($event->notification);

            // Normalize anonymous class names by stripping the runtime suffix
            if (str_contains($notificationClass, '@anonymous')) {
                $notificationClass = preg_replace('/@anonymous.*/', '@anonymous', $notificationClass);
            }

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
                    'request_id' => $this->buffer->getRequestId(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notificationKey(NotificationSending|NotificationSent $event): string
    {
        $notifiableId = method_exists($event->notifiable, 'getKey') ? $event->notifiable->getKey() : spl_object_id($event->notifiable);

        return get_class($event->notification).':'.$event->channel.':'.get_class($event->notifiable).':'.$notifiableId;
    }
}
