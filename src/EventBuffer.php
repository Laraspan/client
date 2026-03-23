<?php

namespace LaraSpan\Client;

class EventBuffer
{
    /** @var array<int, array<string, mixed>> */
    protected array $events = [];

    /** @var array<string, mixed> */
    protected array $context = [];

    protected int $maxEvents;

    /** @var array<string, int> */
    protected array $queryPatterns = [];

    protected int $droppedCount = 0;

    protected bool $bufferFullWarningLogged = false;

    protected ExecutionState $executionState;

    public function __construct(ExecutionState $executionState, int $maxEvents = 5000)
    {
        $this->executionState = $executionState;
        $this->maxEvents = $maxEvents;
        $this->context = ['request_id' => $this->executionState->getRequestId()];
    }

    public function getExecutionState(): ExecutionState
    {
        return $this->executionState;
    }

    public function getStartTime(): float
    {
        return $this->executionState->getStartTime();
    }

    public function pause(): void
    {
        $this->executionState->pause();
    }

    public function resume(): void
    {
        $this->executionState->resume();
    }

    public function isPaused(): bool
    {
        return $this->executionState->isPaused();
    }

    /** @param array<string, mixed> $event */
    public function push(array $event): void
    {
        if ($this->executionState->isPaused()) {
            return;
        }

        if (count($this->events) >= $this->maxEvents) {
            array_shift($this->events);
            $this->droppedCount++;

            if (! $this->bufferFullWarningLogged) {
                $this->bufferFullWarningLogged = true;

                try {
                    logger()->warning('LaraSpan: Event buffer is full, dropping oldest events.', [
                        'max_events' => $this->maxEvents,
                    ]);
                } catch (\Throwable) {
                    // Silently ignore if logger is unavailable
                }
            }
        }

        $this->events[] = array_merge($this->context, $event);
    }

    /** @return array<int, array<string, mixed>> */
    public function flush(): array
    {
        $events = $this->events;
        $this->events = [];
        $this->context = ['request_id' => $this->executionState->getRequestId()];
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
        return $this->executionState->getRequestId();
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

    public function resetEvents(): void
    {
        $this->events = [];
        $this->queryPatterns = [];
        $this->droppedCount = 0;
        $this->bufferFullWarningLogged = false;
    }

    public function reset(): void
    {
        $this->executionState->reset();
        $this->resetEvents();
        $this->context = ['request_id' => $this->executionState->getRequestId()];
    }
}
