<?php

use Illuminate\Log\Events\MessageLogged;
use Illuminate\View\ViewException;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\ExceptionListener;

it('unwraps ViewException to expose underlying cause', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $underlying = new RuntimeException('Column not found');
    $viewException = new ViewException('View error', 0, 1, __FILE__, __LINE__, $underlying);

    $event = new MessageLogged('error', 'View error', ['exception' => $viewException]);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('exception');
    expect($events[0]['payload']['class'])->toBe('RuntimeException');
    expect($events[0]['payload']['message'])->toBe('Column not found');
});

it('keeps ViewException when no previous exception', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $viewException = new ViewException('View error');

    $event = new MessageLogged('error', 'View error', ['exception' => $viewException]);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('exception');
    expect($events[0]['payload']['class'])->toBe('Illuminate\View\ViewException');
    expect($events[0]['payload']['message'])->toBe('View error');
});

it('does not crash on OOM-like errors', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $error = new Error('Allowed memory size of 134217728 bytes exhausted');

    $event = new MessageLogged('error', 'OOM', ['exception' => $error]);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('exception');
    expect($events[0]['payload']['class'])->toBe('Error');
});

it('reserves memory via static method', function () {
    ExceptionListener::reserveMemory();

    $reflection = new ReflectionClass(ExceptionListener::class);
    $property = $reflection->getProperty('reservedMemory');
    $property->setAccessible(true);

    $value = $property->getValue();

    expect(strlen($value))->toBe(32 * 1024);
});

it('does not crash when listener encounters internal error', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $event = new MessageLogged('error', 'Bad context', ['exception' => 'not-a-throwable']);
    $listener->handle($event);

    expect($buffer->count())->toBe(0);
});
