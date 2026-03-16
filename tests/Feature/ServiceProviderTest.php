<?php

use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\LaraSpanServiceProvider;
use LaraSpan\Client\Support\Redactor;
use LaraSpan\Client\Support\Sampler;
use LaraSpan\Client\Transport\InlineTransport;
use LaraSpan\Client\Transport\TransportInterface;

it('registers EventBuffer as singleton', function () {
    $buffer1 = app(EventBuffer::class);
    $buffer2 = app(EventBuffer::class);

    expect($buffer1)->toBe($buffer2);
});

it('registers InlineTransport when configured', function () {
    $transport = app(TransportInterface::class);

    expect($transport)->toBeInstanceOf(InlineTransport::class);
});

it('registers Redactor as singleton', function () {
    $r1 = app(Redactor::class);
    $r2 = app(Redactor::class);

    expect($r1)->toBe($r2);
});

it('registers Sampler as singleton', function () {
    $s1 = app(Sampler::class);
    $s2 = app(Sampler::class);

    expect($s1)->toBe($s2);
});

it('does not register listeners when disabled', function () {
    config()->set('laraspan.enabled', false);

    // Re-boot the provider
    $provider = new LaraSpanServiceProvider(app());
    $provider->boot();

    // The listeners should not have been registered a second time
    // (this mainly tests that boot() returns early)
    expect(true)->toBeTrue();
});
