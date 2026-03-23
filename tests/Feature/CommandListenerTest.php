<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Listeners\CommandListener;
use LaraSpan\Client\Transport\TransportInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

function createCommandInput(array $args = [], ?InputDefinition $definition = null): ArrayInput
{
    $definition ??= new InputDefinition;

    return new ArrayInput($args, $definition);
}

it('captures command finished events', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        expect($events)->toHaveCount(1);
        expect($events[0]['type'])->toBe('command');
        expect($events[0]['payload']['command'])->toBe('app:import');
        expect($events[0]['payload']['exit_code'])->toBe(0);
        expect($events[0]['payload']['duration_ms'])->toBeGreaterThanOrEqual(0);

        return true;
    });

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;
    $input = createCommandInput();

    $startingEvent = new CommandStarting('app:import', $input, $output);
    $listener->handleStarting($startingEvent);

    $finishedEvent = new CommandFinished('app:import', $input, $output, 0);
    $listener->handleFinished($finishedEvent);
});

it('ignores internal laraspan commands', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;
    $input = createCommandInput();

    $startingEvent = new CommandStarting('laraspan:flush', $input, $output);
    $listener->handleStarting($startingEvent);

    $finishedEvent = new CommandFinished('laraspan:flush', $input, $output, 0);
    $listener->handleFinished($finishedEvent);

    expect($buffer->count())->toBe(0);
});

it('ignores schedule commands', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;
    $input = createCommandInput();

    $startingEvent = new CommandStarting('schedule:run', $input, $output);
    $listener->handleStarting($startingEvent);

    $finishedEvent = new CommandFinished('schedule:run', $input, $output, 0);
    $listener->handleFinished($finishedEvent);

    expect($buffer->count())->toBe(0);
});

it('resets buffer on command start', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldNotReceive('send');

    // Push a dummy event into the buffer
    $buffer->push(['type' => 'dummy', 'payload' => []]);
    expect($buffer->count())->toBe(1);

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;
    $input = createCommandInput();

    $startingEvent = new CommandStarting('app:process', $input, $output);
    $listener->handleStarting($startingEvent);

    // Buffer should be cleared by handleStarting
    expect($buffer->count())->toBe(0);
});

it('captures command arguments and options', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        $payload = $events[0]['payload'];
        expect($payload['arguments']['name'])->toBe('users');
        expect($payload['options']['force'])->toBeTrue();

        return true;
    });

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;

    $definition = new InputDefinition([
        new InputArgument('name', InputArgument::REQUIRED),
        new InputOption('force', 'f', InputOption::VALUE_NONE),
    ]);

    $input = new ArrayInput(['name' => 'users', '--force' => true], $definition);

    $startingEvent = new CommandStarting('app:import', $input, $output);
    $listener->handleStarting($startingEvent);

    $finishedEvent = new CommandFinished('app:import', $input, $output, 0);
    $listener->handleFinished($finishedEvent);
});

it('redacts sensitive option values', function () {
    $buffer = app(EventBuffer::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->withArgs(function (array $events) {
        $options = $events[0]['payload']['options'];
        expect($options['password'])->toBe('[REDACTED]');
        expect($options['api-token'])->toBe('[REDACTED]');
        expect($options['secret-key'])->toBe('[REDACTED]');

        return true;
    });

    $listener = new CommandListener($buffer, $transport);
    $output = new NullOutput;

    $definition = new InputDefinition([
        new InputOption('password', null, InputOption::VALUE_REQUIRED),
        new InputOption('api-token', null, InputOption::VALUE_REQUIRED),
        new InputOption('secret-key', null, InputOption::VALUE_REQUIRED),
    ]);

    $input = new ArrayInput([
        '--password' => 'my-secret-pass',
        '--api-token' => 'tok_123',
        '--secret-key' => 'sk_abc',
    ], $definition);

    $startingEvent = new CommandStarting('app:configure', $input, $output);
    $listener->handleStarting($startingEvent);

    $finishedEvent = new CommandFinished('app:configure', $input, $output, 0);
    $listener->handleFinished($finishedEvent);
});
