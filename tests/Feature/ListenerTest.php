<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Events\MessageLogged;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\ExceptionListener;
use LaraSpan\Client\Listeners\QueryListener;
use LaraSpan\Client\Listeners\RequestListener;

it('captures exceptions from MessageLogged events', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $exception = new RuntimeException('Test error');

    $event = new MessageLogged('error', 'Test error', ['exception' => $exception]);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('exception');
    expect($events[0]['payload']['class'])->toBe('RuntimeException');
    expect($events[0]['payload']['message'])->toBe('Test error');
});

it('ignores exceptions on the ignore list', function () {
    config()->set('laraspan.ignore_exceptions', [RuntimeException::class]);

    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $event = new MessageLogged('error', 'Test', ['exception' => new RuntimeException('Ignored')]);
    $listener->handle($event);

    expect($buffer->count())->toBe(0);
});

it('ignores non-error log levels', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(ExceptionListener::class);

    $event = new MessageLogged('info', 'Just info', ['exception' => new RuntimeException('test')]);
    $listener->handle($event);

    expect($buffer->count())->toBe(0);
});

it('captures query events', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(QueryListener::class);

    $event = new QueryExecuted('select * from users where id = ?', [1], 5.2, resolve('db'));
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('query');
    expect($events[0]['payload']['sql'])->toBe('select * from users where id = ?');
    expect($events[0]['payload']['duration_ms'])->toBe(5.2);
});

it('captures request events', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(RequestListener::class);

    $request = Request::create('/test', 'GET');
    $request->attributes->set('laraspan_start_time', microtime(true) - 0.1);

    $response = new Response('OK', 200);

    $event = new RequestHandled($request, $response);
    $listener->handle($event);

    expect($buffer->count())->toBe(1);

    $events = $buffer->flush();
    expect($events[0]['type'])->toBe('request');
    expect($events[0]['payload']['method'])->toBe('GET');
    expect($events[0]['payload']['status_code'])->toBe(200);
    expect($events[0]['payload']['duration_ms'])->toBeGreaterThan(0);
});
