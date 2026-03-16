<?php

namespace LaraSpan\Client\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'laraspan:test';

    protected $description = 'Send a test event to the LaraSpan server';

    public function handle(): int
    {
        $endpoint = config('laraspan.endpoint');
        $token = config('laraspan.token');

        if (empty($token)) {
            $this->error('LARASPAN_TOKEN is not set. Please set it in your .env file.');

            return self::FAILURE;
        }

        $this->components->info("Sending test event to {$endpoint}...");

        $payload = json_encode([
            'token' => $token,
            'sdk_version' => '1.0.0',
            'events' => [
                [
                    'type' => 'request',
                    'occurred_at' => now()->toIso8601String(),
                    'payload' => [
                        'route' => 'laraspan/test',
                        'method' => 'GET',
                        'status_code' => 200,
                        'duration_ms' => 0.1,
                        'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                        'query_count' => 0,
                        'request_id' => 'test-'.uniqid(),
                        'is_slow' => false,
                        'has_n_plus_one' => false,
                    ],
                ],
            ],
        ]);

        try {
            $client = new Client(['timeout' => 10]);

            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
                'body' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            $this->components->info("Server responded with status: {$statusCode}");

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->components->info('Test event sent successfully!');

                return self::SUCCESS;
            }

            $this->components->warn('Unexpected response status code.');

            return self::FAILURE;
        } catch (GuzzleException $e) {
            $this->components->error('Failed to send test event: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
