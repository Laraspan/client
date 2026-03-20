<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Transport\TransportInterface;

class CommandListener
{
    /** @var string[] Commands to ignore to avoid recursion */
    protected array $ignoredCommands = [
        'laraspan:flush',
        'laraspan:test',
        'laraspan:install',
        'laraspan:deploy',
        'schedule:run',
        'schedule:finish',
        'package:discover',
    ];

    /** @var string[] Vendor command prefixes to ignore */
    protected array $vendorCommandPrefixes = [
        'horizon:',
        'reverb:',
        'nova:',
        'telescope:',
        'pulse:',
        'octane:',
        'vapor:',
        'inertia:',
    ];

    public function __construct(
        protected EventBuffer $buffer,
        protected TransportInterface $transport,
    ) {}

    public function handleStarting(CommandStarting $event): void
    {
        if ($this->shouldIgnore($event->command)) {
            return;
        }

        $this->buffer->reset();
    }

    public function handleFinished(CommandFinished $event): void
    {
        if ($this->shouldIgnore($event->command)) {
            return;
        }

        $durationMs = (microtime(true) - $this->buffer->getStartTime()) * 1000;

        $this->buffer->push([
            'type' => 'command',
            'fingerprint' => sha1('command:'.$event->command),
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'command' => $event->command,
                'exit_code' => $event->exitCode,
                'duration_ms' => round($durationMs, 2),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);

        $events = $this->buffer->flush();

        if (! empty($events)) {
            $this->transport->send($events);
        }
    }

    protected function shouldIgnore(?string $command): bool
    {
        if ($command === null) {
            return true;
        }

        if (in_array($command, $this->ignoredCommands)) {
            return true;
        }

        if (config('laraspan.ignore_vendor_events', true)) {
            foreach ($this->vendorCommandPrefixes as $prefix) {
                if (str_starts_with($command, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
