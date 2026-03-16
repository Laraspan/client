<?php

namespace LaraSpan\Client\Console\Commands;

use Illuminate\Console\Command;
use LaraSpan\Client\Jobs\FlushEventsJob;

class FlushCommand extends Command
{
    protected $signature = 'laraspan:flush';

    protected $description = 'Flush buffered LaraSpan events synchronously';

    public function handle(): int
    {
        $this->components->info('Flushing LaraSpan events...');

        try {
            dispatch_sync(new FlushEventsJob);

            $this->components->info('Events flushed successfully.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to flush events: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
