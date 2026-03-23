<?php

use LaraSpan\Client\Transport\HttpSender;
use LaraSpan\Client\Transport\QueueTransport;
use LaraSpan\Client\Transport\TransportInterface;

it('implements TransportInterface', function () {
    $transport = new QueueTransport;

    expect($transport)->toBeInstanceOf(TransportInterface::class);
});

it('does not crash on send when Redis is unavailable', function () {
    // QueueTransport catches Redis exceptions gracefully
    $transport = new QueueTransport(flushThreshold: 100);

    // This should not throw even if Redis is not available
    $transport->send([
        ['type' => 'test', 'payload' => ['data' => 'value']],
    ]);

    expect(true)->toBeTrue();
});

it('handles empty events array', function () {
    $transport = new QueueTransport;

    $transport->send([]);

    // send() returns early for empty arrays without touching Redis
    expect(true)->toBeTrue();
});

it('accepts a custom flush threshold', function () {
    $transport = new QueueTransport(flushThreshold: 50);

    expect($transport)->toBeInstanceOf(QueueTransport::class);
});

it('creates HttpSender from config', function () {
    config()->set('laraspan.url', 'http://localhost:8080');
    config()->set('laraspan.token', 'test-token');
    config()->set('laraspan.transport_timeout', 5);

    $sender = HttpSender::fromConfig();

    expect($sender)->toBeInstanceOf(HttpSender::class);
});
