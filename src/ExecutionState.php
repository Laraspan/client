<?php

namespace LaraSpan\Client;

use Illuminate\Support\Str;

class ExecutionState
{
    public string $requestId;

    public float $startTime;

    public bool $paused = false;

    public ExecutionStage $currentStage = ExecutionStage::Bootstrap;

    public float $stageEnteredAt;

    public array $stageDurations = [];

    public array $pendingMails = [];

    public array $pendingNotifications = [];

    public array $pendingHttpRequests = [];

    public ?string $userId = null;

    public function __construct()
    {
        $this->requestId = Str::uuid()->toString();
        $this->startTime = microtime(true);
        $this->stageEnteredAt = $this->startTime;
        $this->stageDurations = array_fill_keys(
            array_map(fn (ExecutionStage $s) => $s->value, ExecutionStage::forHttp()),
            0.0
        );
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function setUserId(?string $id): void
    {
        $this->userId = $id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function transitionTo(ExecutionStage $stage): void
    {
        $elapsed = (microtime(true) - $this->stageEnteredAt) * 1000;
        $this->stageDurations[$this->currentStage->value] += $elapsed;
        $this->currentStage = $stage;
        $this->stageEnteredAt = microtime(true);
    }

    public function getCurrentStage(): ExecutionStage
    {
        return $this->currentStage;
    }

    public function getStageDurations(): array
    {
        return $this->stageDurations;
    }

    public function getLifecyclePhases(): ?array
    {
        $hasTransitions = false;
        foreach ($this->stageDurations as $duration) {
            if ($duration > 0.0) {
                $hasTransitions = true;
                break;
            }
        }

        if (! $hasTransitions) {
            return null;
        }

        $origin = defined('LARAVEL_START') ? LARAVEL_START : $this->startTime;
        $phases = [];
        $runningMs = 0.0;

        foreach (ExecutionStage::forHttp() as $stage) {
            $duration = $this->stageDurations[$stage->value] ?? 0.0;
            $phases[] = [
                'phase' => $stage->value,
                'start_ms' => round(($this->startTime - $origin) * 1000 + $runningMs, 3),
                'duration_ms' => round($duration, 3),
            ];
            $runningMs += $duration;
        }

        return $phases;
    }

    public function trackPendingMail(string $key, float $startTime): void
    {
        $this->pendingMails[$key] = $startTime;
    }

    public function popPendingMail(string $key): ?float
    {
        if (! isset($this->pendingMails[$key])) {
            return null;
        }

        $time = $this->pendingMails[$key];
        unset($this->pendingMails[$key]);

        return $time;
    }

    public function trackPendingNotification(string $key, float $startTime): void
    {
        $this->pendingNotifications[$key] = $startTime;
    }

    public function popPendingNotification(string $key): ?float
    {
        if (! isset($this->pendingNotifications[$key])) {
            return null;
        }

        $time = $this->pendingNotifications[$key];
        unset($this->pendingNotifications[$key]);

        return $time;
    }

    public function trackPendingHttpRequest(string $key, float $startTime): void
    {
        $this->pendingHttpRequests[$key] = $startTime;
    }

    public function popPendingHttpRequest(string $key): ?float
    {
        if (! isset($this->pendingHttpRequests[$key])) {
            return null;
        }

        $time = $this->pendingHttpRequests[$key];
        unset($this->pendingHttpRequests[$key]);

        return $time;
    }

    public function reset(): void
    {
        $this->requestId = Str::uuid()->toString();
        $this->startTime = microtime(true);
        $this->stageEnteredAt = $this->startTime;
        $this->paused = false;
        $this->currentStage = ExecutionStage::Bootstrap;
        $this->stageDurations = array_fill_keys(
            array_map(fn (ExecutionStage $s) => $s->value, ExecutionStage::forHttp()),
            0.0
        );
        $this->pendingMails = [];
        $this->pendingNotifications = [];
        $this->pendingHttpRequests = [];
        $this->userId = null;
    }
}
