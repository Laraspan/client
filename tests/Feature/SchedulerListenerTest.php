<?php

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Listeners\ScheduledTaskSkippedListener;
use LaraSpan\Client\Listeners\SchedulerListener;
use LaraSpan\Client\Transport\TransportInterface;

function createMockTask(array $overrides = []): ScheduledEvent
{
    $task = Mockery::mock(ScheduledEvent::class)->makePartial();
    $task->command = array_key_exists('command', $overrides) ? $overrides['command'] : 'app:send-reminders';
    $task->description = $overrides['description'] ?? null;
    $task->expression = $overrides['expression'] ?? '* * * * *';
    $task->timezone = $overrides['timezone'] ?? 'UTC';
    $task->exitCode = $overrides['exitCode'] ?? 0;
    $task->withoutOverlapping = $overrides['withoutOverlapping'] ?? false;
    $task->onOneServer = $overrides['onOneServer'] ?? false;
    $task->runInBackground = $overrides['runInBackground'] ?? false;
    $task->evenInMaintenanceMode = $overrides['evenInMaintenanceMode'] ?? false;

    return $task;
}

it('captures scheduled task finished events', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        expect($events)->toHaveCount(1);
        expect($events[0]['type'])->toBe('scheduler');
        expect($events[0]['payload']['status'])->toBe('processed');
        expect($events[0]['payload']['command'])->toBe('app:send-reminders');
        expect($events[0]['payload']['expression'])->toBe('*/5 * * * *');
        expect($events[0]['payload']['timezone'])->toBe('America/New_York');
        expect($events[0]['payload']['exit_code'])->toBe(0);
        expect($events[0]['payload']['is_failed'])->toBeFalse();
        expect($events[0]['payload']['duration_ms'])->toBe(1500.0);

        return true;
    });

    $listener = new SchedulerListener($buffer, $transport);

    $task = createMockTask([
        'command' => 'app:send-reminders',
        'expression' => '*/5 * * * *',
        'timezone' => 'America/New_York',
        'exitCode' => 0,
    ]);

    $event = new ScheduledTaskFinished($task, runtime: 1.5);
    $listener->handle($event);
});

it('captures failed scheduled tasks', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        expect($events[0]['payload']['status'])->toBe('failed');
        expect($events[0]['payload']['is_failed'])->toBeTrue();
        expect($events[0]['payload']['exit_code'])->toBe(1);

        return true;
    });

    $listener = new SchedulerListener($buffer, $transport);

    $task = createMockTask(['exitCode' => 1]);

    $event = new ScheduledTaskFinished($task, runtime: 0.5);
    $listener->handle($event);
});

it('skips LaraSpan flush job tasks', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    $listener = new SchedulerListener($buffer, $transport);

    $task = createMockTask([
        'command' => null,
        'description' => FlushEventsJob::class,
    ]);

    $event = new ScheduledTaskFinished($task, runtime: 0.1);
    $listener->handle($event);

    expect($buffer->count())->toBe(0);
});

it('captures scheduled task skipped events', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        expect($events)->toHaveCount(1);
        expect($events[0]['type'])->toBe('scheduler');
        expect($events[0]['payload']['status'])->toBe('skipped');
        expect($events[0]['payload']['duration_ms'])->toBe(0);
        expect($events[0]['payload']['is_failed'])->toBeFalse();
        expect($events[0]['payload']['exit_code'])->toBeNull();

        return true;
    });

    $listener = new ScheduledTaskSkippedListener($buffer, $transport);

    $task = createMockTask([
        'command' => 'app:cleanup',
        'expression' => '0 * * * *',
    ]);

    $event = new ScheduledTaskSkipped($task);
    $listener->handle($event);
});

it('does not crash on listener errors', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);

    $listener = new SchedulerListener($buffer, $transport);

    // Create a task mock that throws when accessing the expression property
    $task = Mockery::mock(ScheduledEvent::class)->makePartial();
    $task->command = 'app:broken';
    $task->exitCode = 0;
    $task->shouldReceive('__get')->with('expression')->andThrow(new RuntimeException('broken property'));

    $event = new ScheduledTaskFinished($task, runtime: 0.1);

    // Should not throw - the listener catches exceptions internally
    $listener->handle($event);

    expect(true)->toBeTrue();
});
