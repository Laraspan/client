<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use LaraSpan\Client\EventBuffer;

class CacheListener
{
    protected array $vendorPrefixes = [
        'laravel_cache:',
        'illuminate:',
        'nova:',
        'pulse:',
        'reverb:',
        'telescope:',
        'horizon:',
    ];

    public function __construct(protected EventBuffer $buffer) {}

    public function handleHit(CacheHit $event): void
    {
        $this->record($event->key, 'hit', $event->tags ?? [], $event->storeName ?? 'default');
    }

    public function handleMissed(CacheMissed $event): void
    {
        $this->record($event->key, 'miss', $event->tags ?? [], $event->storeName ?? 'default');
    }

    public function handleWritten(KeyWritten $event): void
    {
        $this->record($event->key, 'write', $event->tags ?? [], $event->storeName ?? 'default');
    }

    public function handleForgotten(KeyForgotten $event): void
    {
        $this->record($event->key, 'forget', $event->tags ?? [], $event->storeName ?? 'default');
    }

    protected function isVendorKey(string $key): bool
    {
        foreach ($this->vendorPrefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function record(string $key, string $operation, array $tags, string $store): void
    {
        if (config('laraspan.ignore_vendor_events', true) && $this->isVendorKey($key)) {
            return;
        }

        $this->buffer->push([
            'type' => 'cache',
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'key' => $key,
                'operation' => $operation,
                'tags' => $tags,
                'store' => $store,
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
