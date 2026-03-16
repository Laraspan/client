<?php

namespace LaraSpan\Client\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FlushEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 60];

    public int $uniqueFor = 10;

    public function handle(): void
    {
        $maxBatchSize = config('laraspan.buffer.max_batch_size', 500);
        $endpoint = config('laraspan.endpoint');
        $token = config('laraspan.token');

        $events = [];

        for ($i = 0; $i < $maxBatchSize; $i++) {
            $raw = Redis::connection('default')->command('lpop', ['laraspan:events']);

            if ($raw === null) {
                break;
            }

            $decoded = json_decode($raw, true);

            if ($decoded !== null) {
                $events[] = $decoded;
            }
        }

        if (empty($events)) {
            return;
        }

        try {
            $payload = json_encode([
                'token' => $token,
                'sdk_version' => '1.0.0',
                'events' => $events,
            ]);

            $compressed = gzencode($payload);

            $client = new Client(['timeout' => 10]);

            $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Content-Encoding' => 'gzip',
                    'Authorization' => 'Bearer '.$token,
                ],
                'body' => $compressed,
            ]);

            // If more events remain, dispatch another flush job
            $remaining = (int) Redis::connection('default')->command('llen', ['laraspan:events']);

            if ($remaining > 0) {
                self::dispatch();
            }
        } catch (GuzzleException $e) {
            Log::warning('LaraSpan: Failed to flush events to server, re-queuing.', [
                'error' => $e->getMessage(),
                'event_count' => count($events),
            ]);

            // Re-push events to Redis for retry
            $encoded = array_map('json_encode', $events);
            Redis::connection('default')->command('rpush', ['laraspan:events', ...$encoded]);

            throw $e;
        }
    }
}
