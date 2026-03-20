<?php

namespace LaraSpan\Client\Transport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use LaraSpan\Client\Jobs\FlushEventsJob;
use RedisException;

class QueueTransport implements TransportInterface
{
    public function __construct(
        protected int $flushThreshold = 100,
    ) {}

    /** @param array<int, array<string, mixed>> $events */
    public function send(array $events): void
    {
        if (empty($events)) {
            return;
        }

        try {
            $encoded = array_map('json_encode', $events);

            $redis = Redis::connection(config('laraspan.redis_connection', 'default'));

            $redis->command('rpush', ['laraspan:events', ...$encoded]);

            $length = (int) $redis->command('llen', ['laraspan:events']);

            if ($length > config('laraspan.max_queue_size', 50000)) {
                $redis->command('ltrim', ['laraspan:events', -config('laraspan.max_queue_size', 50000), -1]);
            }

            if ($length >= $this->flushThreshold) {
                FlushEventsJob::dispatch();
            }
        } catch (RedisException|\Exception $e) {
            Log::warning('LaraSpan: Failed to buffer events to Redis.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
