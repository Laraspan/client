<?php

namespace LaraSpan\Client\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class InlineTransport implements TransportInterface
{
    public function __construct(
        protected string $endpoint = '',
        protected string $token = '',
    ) {}

    /** @param array<int, array<string, mixed>> $events */
    public function send(array $events): void
    {
        if (empty($events)) {
            return;
        }

        try {
            $payload = json_encode([
                'token' => $this->token,
                'sdk_version' => '1.0.0',
                'events' => $events,
            ]);

            $compressed = gzencode($payload);

            $client = new Client(['timeout' => 5]);

            $client->post($this->endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Content-Encoding' => 'gzip',
                    'Authorization' => 'Bearer '.$this->token,
                ],
                'body' => $compressed,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('LaraSpan: Failed to send events inline.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
