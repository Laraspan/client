<?php

namespace LaraSpan\Client\Transport;

interface TransportInterface
{
    /** @param array<int, array<string, mixed>> $events */
    public function send(array $events): void;
}
