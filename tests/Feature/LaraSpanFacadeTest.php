<?php

use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\LaraSpan;

it('pause prevents events from being captured', function () {
    $buffer = app(EventBuffer::class);

    LaraSpan::pause();
    $buffer->push(['type' => 'test', 'payload' => []]);
    LaraSpan::resume();

    expect($buffer->flush())->toBeEmpty();
});

it('resume re-enables event capture', function () {
    $buffer = app(EventBuffer::class);

    LaraSpan::pause();
    LaraSpan::resume();
    $buffer->push(['type' => 'test', 'payload' => []]);

    expect($buffer->flush())->toHaveCount(1);
});

it('ignore pauses during callback and resumes after', function () {
    $buffer = app(EventBuffer::class);

    $result = LaraSpan::ignore(function () use ($buffer) {
        $buffer->push(['type' => 'ignored', 'payload' => []]);
        return 'done';
    });

    $buffer->push(['type' => 'captured', 'payload' => []]);

    expect($result)->toBe('done');
    $events = $buffer->flush();
    expect($events)->toHaveCount(1);
    expect($events[0]['type'])->toBe('captured');
});

it('ignore resumes even if callback throws', function () {
    $buffer = app(EventBuffer::class);

    try {
        LaraSpan::ignore(function () {
            throw new RuntimeException('test');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($buffer->isPaused())->toBeFalse();
    $buffer->push(['type' => 'after-error', 'payload' => []]);
    expect($buffer->flush())->toHaveCount(1);
});
