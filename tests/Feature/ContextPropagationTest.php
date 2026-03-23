<?php

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\JobListener;

it('injects trace_id into queue job payload', function () {
    $buffer = app(EventBuffer::class);
    $requestId = $buffer->getRequestId();

    $payload = app()->call(function () use ($buffer) {
        return [
            'laraspan' => [
                'trace_id' => $buffer->getRequestId(),
            ],
        ];
    });

    expect($payload)->toHaveKey('laraspan.trace_id')
        ->and($payload['laraspan']['trace_id'])->toBe($requestId);
});

it('sets parent_request_id on job processing', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(JobListener::class);

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $job->shouldReceive('getRawBody')->andReturn(json_encode([
        'laraspan' => ['trace_id' => 'parent-uuid-123'],
    ]));

    $event = new JobProcessing('sync', $job);
    $listener->handleProcessing($event);

    $buffer->push([
        'type' => 'test',
        'payload' => ['foo' => 'bar'],
    ]);

    $events = $buffer->flush();

    expect($events[0])->toHaveKey('parent_request_id')
        ->and($events[0]['parent_request_id'])->toBe('parent-uuid-123');
});

it('handles missing laraspan data in job payload', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(JobListener::class);

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $job->shouldReceive('getRawBody')->andReturn(json_encode([
        'displayName' => 'App\\Jobs\\TestJob',
    ]));

    $event = new JobProcessing('sync', $job);
    $listener->handleProcessing($event);

    $buffer->push([
        'type' => 'test',
        'payload' => ['foo' => 'bar'],
    ]);

    $events = $buffer->flush();

    expect($events[0])->not->toHaveKey('parent_request_id');
});

it('resets buffer and generates new request_id on job processing', function () {
    $buffer = app(EventBuffer::class);
    $listener = app(JobListener::class);

    $originalRequestId = $buffer->getRequestId();

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $job->shouldReceive('getRawBody')->andReturn(json_encode([
        'displayName' => 'App\\Jobs\\TestJob',
    ]));

    $event = new JobProcessing('sync', $job);
    $listener->handleProcessing($event);

    $newRequestId = $buffer->getRequestId();

    expect($newRequestId)->not->toBe($originalRequestId);
});
