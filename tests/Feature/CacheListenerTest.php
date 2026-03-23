<?php

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\CacheListener;

it('captures cache hit events', function () {
    $buffer = app(EventBuffer::class);

    $event = new CacheHit('default', 'users.1', 'some-value', []);
    app(CacheListener::class)->handleHit($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('cache');
    expect($events[0]['payload']['operation'])->toBe('hit');
    expect($events[0]['payload']['key'])->toBe('users.1');
});

it('captures cache miss events', function () {
    $buffer = app(EventBuffer::class);

    $event = new CacheMissed('default', 'users.2');
    app(CacheListener::class)->handleMissed($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('cache');
    expect($events[0]['payload']['operation'])->toBe('miss');
    expect($events[0]['payload']['key'])->toBe('users.2');
});

it('captures cache write events with ttl', function () {
    $buffer = app(EventBuffer::class);

    $event = new KeyWritten('default', 'users.3', 'value', 3600);
    app(CacheListener::class)->handleWritten($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('cache');
    expect($events[0]['payload']['operation'])->toBe('write');
    expect($events[0]['payload']['key'])->toBe('users.3');
    expect($events[0]['payload']['ttl_seconds'])->toBe(3600);
});

it('captures cache write events without ttl', function () {
    $buffer = app(EventBuffer::class);

    $event = new KeyWritten('default', 'users.4', 'value', null);
    app(CacheListener::class)->handleWritten($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['payload']['operation'])->toBe('write');
    expect($events[0]['payload'])->not->toHaveKey('ttl_seconds');
});

it('captures cache forget events', function () {
    $buffer = app(EventBuffer::class);

    $event = new KeyForgotten('default', 'users.5');
    app(CacheListener::class)->handleForgotten($event);

    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('cache');
    expect($events[0]['payload']['operation'])->toBe('forget');
    expect($events[0]['payload']['key'])->toBe('users.5');
});

it('skips session-like keys', function () {
    $buffer = app(EventBuffer::class);

    // 40-char hex string resembling a session ID
    $sessionKey = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0';
    $event = new CacheHit('default', $sessionKey, 'session-data');
    app(CacheListener::class)->handleHit($event);

    expect($buffer->flush())->toBeEmpty();
});

it('does not crash on listener errors', function () {
    $buffer = app(EventBuffer::class);

    // Create a mock event that will cause issues inside record()
    // by using a valid event first, then verifying the listener
    // gracefully handles the error via try/catch
    $mock = Mockery::mock(CacheHit::class);
    $mock->key = 'test-key';
    $mock->tags = [];
    $mock->storeName = 'default';

    // Force an error by making the buffer throw
    $brokenBuffer = Mockery::mock(EventBuffer::class);
    $brokenBuffer->shouldReceive('getRequestId')->andThrow(new RuntimeException('Buffer error'));

    $listener = new CacheListener($brokenBuffer);

    // Should not throw — the try/catch in handleHit should catch it
    $listener->handleHit($mock);

    // If we reach here, the listener handled the error gracefully
    expect(true)->toBeTrue();
});
