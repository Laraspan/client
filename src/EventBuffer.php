<?php

namespace LaraSpan\Client;

use Illuminate\Support\Str;

class EventBuffer
{
    /** @var array<int, array<string, mixed>> */
    protected array $events = [];

    /** @var array<string, mixed> */
    protected array $context = [];

    protected string $requestId;

    protected int $maxEvents;

    /** @var array<string, int> */
    protected array $queryPatterns = [];

    protected bool $paused = false;

    public function __construct(int $maxEvents = 5000)
    {
        $this->requestId = (string) Str::uuid();
        $this->maxEvents = $maxEvents;
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

    /** @param array<string, mixed> $event */
    public function push(array $event): void
    {
        if ($this->paused) {
            return;
        }

        if (count($this->events) >= $this->maxEvents) {
            return;
        }

        $this->events[] = array_merge($event, $this->context);
    }

    /** @return array<int, array<string, mixed>> */
    public function flush(): array
    {
        $events = $this->events;
        $this->events = [];
        $this->context = [];
        $this->queryPatterns = [];

        return $events;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function trackQueryPattern(string $normalizedSql): void
    {
        $this->queryPatterns[$normalizedSql] = ($this->queryPatterns[$normalizedSql] ?? 0) + 1;
    }

    public function getQueryPatternCount(string $normalizedSql): int
    {
        return $this->queryPatterns[$normalizedSql] ?? 0;
    }

    public function hasNPlusOne(int $threshold): bool
    {
        foreach ($this->queryPatterns as $count) {
            if ($count >= $threshold) {
                return true;
            }
        }

        return false;
    }

    public function getQueryCount(): int
    {
        return array_sum($this->queryPatterns);
    }

    public function reset(): void
    {
        $this->events = [];
        $this->context = [];
        $this->queryPatterns = [];
        $this->paused = false;
        $this->requestId = (string) Str::uuid();
    }
}
