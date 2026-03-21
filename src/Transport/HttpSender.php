<?php

namespace LaraSpan\Client\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HttpSender
{
    public const SDK_VERSION = '2.0.0';

    public function __construct(
        protected string $baseUrl,
        protected string $token,
        protected int $timeout = 10,
    ) {}

    /**
     * Send events to the LaraSpan ingest endpoint.
     *
     * @param  array<int, array<string, mixed>>  $events
     *
     * @throws GuzzleException
     */
    public function send(array $events, bool $compress = true): int
    {
        return $this->post('/api/v1/ingest', [
            'sdk_version' => self::SDK_VERSION,
            'events' => $events,
        ], $compress);
    }

    /**
     * Send a deploy notification.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws GuzzleException
     */
    public function deploy(array $data): int
    {
        return $this->post('/api/v1/deploy', $data, compress: false);
    }

    /**
     * @throws GuzzleException
     */
    protected function post(string $path, array $data, bool $compress = true): int
    {
        $payload = json_encode($data);

        if ($payload === false) {
            Log::warning('LaraSpan: Failed to JSON-encode event payload.', [
                'error' => json_last_error_msg(),
            ]);

            return 0;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];

        $body = $payload;

        if ($compress) {
            $body = gzencode($payload);
            if ($body === false) {
                $body = $payload;
                unset($headers['Content-Encoding']);
            } else {
                $headers['Content-Encoding'] = 'gzip';
            }
        }

        $client = new Client(['timeout' => $this->timeout]);

        $response = $client->post(rtrim($this->baseUrl, '/').$path, [
            'headers' => $headers,
            'body' => $body,
        ]);

        return $response->getStatusCode();
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('laraspan.url', ''),
            token: config('laraspan.token', ''),
            timeout: config('laraspan.transport_timeout', 5),
        );
    }
}
