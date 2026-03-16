<?php

namespace LaraSpan\Client\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'laraspan:install';

    protected $description = 'Install LaraSpan client configuration';

    public function handle(): int
    {
        $this->components->info('Publishing LaraSpan configuration...');
        $this->call('vendor:publish', ['--tag' => 'laraspan-config']);

        $this->appendEnvStubs('.env');
        $this->appendEnvStubs('.env.example');

        $this->components->info('LaraSpan client installed successfully!');
        $this->components->info('Next steps:');
        $this->line('  1. Set LARASPAN_TOKEN in your .env file');
        $this->line('  2. Set LARASPAN_URL to your LaraSpan server URL');
        $this->line('  3. Run `php artisan laraspan:test` to verify the connection');

        return self::SUCCESS;
    }

    protected function appendEnvStubs(string $file): void
    {
        $path = base_path($file);

        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if (str_contains($contents, 'LARASPAN_TOKEN')) {
            return;
        }

        $stubs = "\n# LaraSpan Monitoring\nLARASPAN_TOKEN=\nLARASPAN_URL=http://localhost:8080\nLARASPAN_TRANSPORT=queue\n";

        file_put_contents($path, $contents . $stubs);

        $this->line("Updated {$file} with LaraSpan environment variables.");
    }
}
