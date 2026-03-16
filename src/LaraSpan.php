<?php

namespace LaraSpan\Client;

use LaraSpan\Client\Support\Sampler;

class LaraSpan
{
    public static function pause(): void
    {
        app(EventBuffer::class)->pause();
    }

    public static function resume(): void
    {
        app(EventBuffer::class)->resume();
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
}
