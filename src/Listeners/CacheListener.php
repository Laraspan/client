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
        try {
            $this->record($event->key, 'hit', $event->tags ?? [], $event->storeName ?? 'default');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleMissed(CacheMissed $event): void
    {
        try {
            $this->record($event->key, 'miss', $event->tags ?? [], $event->storeName ?? 'default');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleWritten(KeyWritten $event): void
    {
        try {
            $this->record($event->key, 'write', $event->tags ?? [], $event->storeName ?? 'default', $event->seconds ?? null);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleForgotten(KeyForgotten $event): void
    {
        try {
            $this->record($event->key, 'forget', $event->tags ?? [], $event->storeName ?? 'default');
        } catch (\Throwable $e) {
            report($e);
        }
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

    protected function isSessionKey(string $key): bool
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/i', $key);
    }

    protected function record(string $key, string $operation, array $tags, string $store, ?int $ttlSeconds = null): void
    {
        if (config('laraspan.ignore_vendor_events', true) && $this->isVendorKey($key)) {
            return;
        }

        if ($this->isSessionKey($key)) {
            return;
        }

        $payload = [
            'key' => $key,
            'operation' => $operation,
            'tags' => $tags,
            'store' => $store,
            'request_id' => $this->buffer->getRequestId(),
        ];

        if ($operation === 'write' && $ttlSeconds !== null) {
            $payload['ttl_seconds'] = $ttlSeconds;
        }

        $this->buffer->push([
            'type' => 'cache',
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ]);
    }
}
