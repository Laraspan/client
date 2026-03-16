<?php

namespace LaraSpan\Client\Transport;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class InlineTransport implements TransportInterface
{
    public function __construct(
        protected string $baseUrl = '',
        protected string $token = '',
    ) {}

    /** @param array<int, array<string, mixed>> $events */
    public function send(array $events): void
    {
        if (empty($events)) {
            return;
        }

        try {
            $sender = new HttpSender($this->baseUrl, $this->token, timeout: 5);
            $sender->send($events);
        } catch (GuzzleException $e) {
            Log::warning('LaraSpan: Failed to send events inline.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
