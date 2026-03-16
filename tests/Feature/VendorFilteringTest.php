<?php

use Illuminate\Cache\Events\CacheHit;
use LaraSpan\Client\EventBuffer;

it('cache listener ignores vendor cache keys', function () {
    config()->set('laraspan.ignore_vendor_events', true);
    $buffer = app(EventBuffer::class);

    $event = new CacheHit('default', 'laravel_cache:data', null);
    app(\LaraSpan\Client\Listeners\CacheListener::class)->handleHit($event);

    expect($buffer->flush())->toBeEmpty();
});

it('cache listener captures non-vendor keys', function () {
    config()->set('laraspan.ignore_vendor_events', true);
    $buffer = app(EventBuffer::class);

    $event = new CacheHit('default', 'users:1', null);
    app(\LaraSpan\Client\Listeners\CacheListener::class)->handleHit($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('cache');
});

it('cache listener captures vendor keys when ignore disabled', function () {
    config()->set('laraspan.ignore_vendor_events', false);
    $buffer = app(EventBuffer::class);

    $event = new CacheHit('default', 'laravel_cache:data', null);
    app(\LaraSpan\Client\Listeners\CacheListener::class)->handleHit($event);

    expect($buffer->flush())->toHaveCount(1);
});

it('command listener ignores vendor commands', function () {
    config()->set('laraspan.ignore_vendor_events', true);

    $listener = app(\LaraSpan\Client\Listeners\CommandListener::class);

    // Use reflection to test shouldIgnore
    $method = new ReflectionMethod($listener, 'shouldIgnore');

    expect($method->invoke($listener, 'horizon:snapshot'))->toBeTrue();
    expect($method->invoke($listener, 'reverb:start'))->toBeTrue();
    expect($method->invoke($listener, 'my:custom-command'))->toBeFalse();
});
