<?php

namespace LaraSpan\Client\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeployCommand extends Command
{
    protected $signature = 'laraspan:deploy
        {--version= : The deployment version (e.g., 1.2.0)}
        {--commit= : The git commit hash}
        {--deployer= : The person who deployed}';

    protected $description = 'Record a deployment in LaraSpan';

    public function handle(): int
    {
        $endpoint = config('laraspan.endpoint', '');
        $token = config('laraspan.token', '');

        if (! $endpoint || ! $token) {
            $this->error('LaraSpan is not configured. Set LARASPAN_ENDPOINT and LARASPAN_TOKEN.');

            return self::FAILURE;
        }

        $deployUrl = str_replace('/api/ingest', '/api/deploy', $endpoint);

        $version = $this->option('version');
        if (! $version) {
            $this->error('The --version option is required.');

            return self::FAILURE;
        }

        $commit = $this->option('commit') ?? $this->detectCommit();
        $deployer = $this->option('deployer') ?? $this->detectDeployer();

        $this->info("Recording deployment v{$version}...");

        $response = Http::withToken($token)
            ->timeout(10)
            ->post($deployUrl, [
                'version' => $version,
                'commit' => $commit,
                'deployer' => $deployer,
            ]);

        if ($response->successful()) {
            $this->info('Deployment recorded successfully.');

            return self::SUCCESS;
        }

        $this->error("Failed to record deployment: {$response->status()} {$response->body()}");

        return self::FAILURE;
    }

    protected function detectCommit(): ?string
    {
        $commit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));

        return $commit !== '' ? $commit : null;
    }

    protected function detectDeployer(): ?string
    {
        $name = trim((string) shell_exec('git config user.name 2>/dev/null'));

        return $name !== '' ? $name : null;
    }
}
