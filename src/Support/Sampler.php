<?php

namespace LaraSpan\Client\Support;

class Sampler
{
    /** @var array<string, float> */
    protected array $rates;

    protected ?float $override = null;

    /** @param array<string, float> $rates */
    public function __construct(array $rates = [])
    {
        $this->rates = $rates;
    }

    public function setOverride(?float $rate): void
    {
        $this->override = $rate;
    }

    public function shouldSample(string $eventType): bool
    {
        // Exceptions always pass through
        if ($eventType === 'exception') {
            return true;
        }

        // Per-route override applies to all event types in the request
        if ($this->override !== null) {
            return $this->checkRate($this->override);
        }

        $rate = $this->rates[$eventType] ?? 1.0;

        return $this->checkRate($rate);
    }

    protected function checkRate(float $rate): bool
    {
        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $rate;
    }
}
