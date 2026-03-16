<?php

use LaraSpan\Client\Transport\InlineTransport;

it('sends events via inline transport', function () {
    // We test that InlineTransport doesn't throw on send
    // In a real test we'd mock Guzzle, but here we verify the class works
    $transport = new InlineTransport(
        endpoint: 'http://localhost:9999/api/ingest',
        token: 'test-token',
    );

    // This will fail to connect but should catch the exception gracefully
    $transport->send([
        ['type' => 'test', 'payload' => ['data' => 'value']],
    ]);

    // If we get here, the exception was caught gracefully
    expect(true)->toBeTrue();
});

it('does nothing with empty events', function () {
    $transport = new InlineTransport(
        endpoint: 'http://localhost:9999/api/ingest',
        token: 'test-token',
    );

    $transport->send([]);

    expect(true)->toBeTrue();
});
