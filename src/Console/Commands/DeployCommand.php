<?php

namespace LaraSpan\Client\Console\Commands;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use LaraSpan\Client\Transport\HttpSender;

class DeployCommand extends Command
{
    protected $signature = 'laraspan:deploy
        {--ver= : The deployment version (e.g., 1.2.0)}
        {--commit= : The git commit hash}
        {--deployer= : The person who deployed}';

    protected $description = 'Record a deployment in LaraSpan';

    public function handle(): int
    {
        $baseUrl = config('laraspan.url', '');
        $token = config('laraspan.token', '');

        if (! $baseUrl || ! $token) {
            $this->error('LaraSpan is not configured. Set LARASPAN_URL and LARASPAN_TOKEN.');

            return self::FAILURE;
        }

        $version = $this->option('ver');
        if (! $version) {
            $this->error('The --ver option is required.');

            return self::FAILURE;
        }

        $commit = $this->option('commit') ?? $this->detectCommit();
        $deployer = $this->option('deployer') ?? $this->detectDeployer();

        $this->info("Recording deployment v{$version}...");

        try {
            $sender = new HttpSender($baseUrl, $token);
            $sender->deploy([
                'version' => $version,
                'commit' => $commit,
                'deployer' => $deployer,
            ]);

            $this->info('Deployment recorded successfully.');

            return self::SUCCESS;
        } catch (GuzzleException $e) {
            $this->error('Failed to record deployment: '.$e->getMessage());

            return self::FAILURE;
        }
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
