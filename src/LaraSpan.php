<?php

namespace LaraSpan\Client;

use Closure;
use LaraSpan\Client\Support\Sampler;
use LaraSpan\Client\Support\UserProvider;

class LaraSpan
{
    public static function pause(): void
    {
        app(ExecutionState::class)->pause();
    }

    public static function resume(): void
    {
        app(ExecutionState::class)->resume();
    }

    public static function ignore(callable $callback): mixed
    {
        static::pause();

        try {
            return $callback();
        } finally {
            static::resume();
        }
    }

    public static function sample(float $rate = 1.0): void
    {
        app(Sampler::class)->setOverride($rate);
    }

    public static function dontSample(): void
    {
        app(Sampler::class)->setOverride(0.0);
    }

    public static function user(Closure $callback): void
    {
        app(UserProvider::class)->setResolver($callback);
    }
}
