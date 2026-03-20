<?php

namespace LaraSpan\Client\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use LaraSpan\Client\Transport\HttpSender;

class FlushEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 60];

    public int $uniqueFor = 10;

    public function handle(): void
    {
        $events = $this->popEventsFromRedis();

        if (empty($events)) {
            return;
        }

        try {
            HttpSender::fromConfig()->send($events);

            $this->dispatchNextIfNeeded();
        } catch (GuzzleException $e) {
            Log::warning('LaraSpan: Failed to flush events to server, re-queuing.', [
                'error' => $e->getMessage(),
                'event_count' => count($events),
            ]);

            $this->reQueueEvents($events);

            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function popEventsFromRedis(): array
    {
        $maxBatchSize = config('laraspan.buffer.max_batch_size', 500);
        $events = [];

        for ($i = 0; $i < $maxBatchSize; $i++) {
            $raw = Redis::connection(config('laraspan.redis_connection', 'default'))->command('lpop', ['laraspan:events']);

            if ($raw === null) {
                break;
            }

            $decoded = json_decode($raw, true);

            if ($decoded !== null) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    protected function dispatchNextIfNeeded(): void
    {
        $remaining = (int) Redis::connection(config('laraspan.redis_connection', 'default'))->command('llen', ['laraspan:events']);

        if ($remaining > 0) {
            self::dispatch();
        }
    }

    /** @param array<int, array<string, mixed>> $events */
    protected function reQueueEvents(array $events): void
    {
        $encoded = array_map('json_encode', $events);
        Redis::connection(config('laraspan.redis_connection', 'default'))->command('rpush', ['laraspan:events', ...$encoded]);
    }
}
