<?php

use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\ExecutionState;

it('pushes and flushes events', function () {
    $buffer = new EventBuffer(new ExecutionState);
    $buffer->push(['type' => 'test', 'data' => 'foo']);
    $buffer->push(['type' => 'test', 'data' => 'bar']);

    expect($buffer->count())->toBe(2);

    $events = $buffer->flush();

    expect($events)->toHaveCount(2);
    expect($buffer->count())->toBe(0);
});

it('merges context into events', function () {
    $buffer = new EventBuffer(new ExecutionState);
    $buffer->setContext(['request_id' => 'abc-123']);
    $buffer->push(['type' => 'test']);

    $events = $buffer->flush();

    expect($events[0]['request_id'])->toBe('abc-123');
});

it('generates a UUID request id', function () {
    $buffer = new EventBuffer(new ExecutionState);

    expect($buffer->getRequestId())
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('enforces max events cap by dropping oldest', function () {
    $buffer = new EventBuffer(new ExecutionState, maxEvents: 3);

    $buffer->push(['type' => 'a']);
    $buffer->push(['type' => 'b']);
    $buffer->push(['type' => 'c']);
    $buffer->push(['type' => 'd']); // oldest ('a') is dropped

    expect($buffer->count())->toBe(3);

    $events = $buffer->flush();
    $types = array_column($events, 'type');
    expect($types)->toBe(['b', 'c', 'd']);
});

it('tracks query patterns', function () {
    $buffer = new EventBuffer(new ExecutionState);

    $buffer->trackQueryPattern('select * from users where id = [int]');
    $buffer->trackQueryPattern('select * from users where id = [int]');
    $buffer->trackQueryPattern('select * from posts where id = [int]');

    expect($buffer->getQueryPatternCount('select * from users where id = [int]'))->toBe(2);
    expect($buffer->getQueryPatternCount('select * from posts where id = [int]'))->toBe(1);
    expect($buffer->getQueryCount())->toBe(3);
});

it('detects n+1 queries', function () {
    $buffer = new EventBuffer(new ExecutionState);

    for ($i = 0; $i < 5; $i++) {
        $buffer->trackQueryPattern('select * from users where id = [int]');
    }

    expect($buffer->hasNPlusOne(threshold: 5))->toBeTrue();
    expect($buffer->hasNPlusOne(threshold: 6))->toBeFalse();
});

it('resets all state', function () {
    $buffer = new EventBuffer(new ExecutionState);
    $originalId = $buffer->getRequestId();

    $buffer->push(['type' => 'test']);
    $buffer->setContext(['foo' => 'bar']);
    $buffer->trackQueryPattern('select 1');

    $buffer->reset();

    expect($buffer->count())->toBe(0);
    expect($buffer->getQueryCount())->toBe(0);
    expect($buffer->getRequestId())->not->toBe($originalId);
});
