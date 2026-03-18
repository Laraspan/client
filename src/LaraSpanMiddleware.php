<?php

namespace LaraSpan\Client;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LaraSpanMiddleware
{
    protected static ?float $handleEntryTime = null;

    protected static ?float $nextCallTime = null;

    protected static ?float $nextReturnTime = null;

    protected static ?float $handleReturnTime = null;

    protected static ?float $terminateEntryTime = null;

    public function __construct(protected EventBuffer $buffer) {}

    public function handle(Request $request, Closure $next): Response
    {
        static::$handleEntryTime = microtime(true);

        if ($this->shouldIgnore($request)) {
            $this->buffer->pause();
        }

        static::$nextCallTime = microtime(true);
        $response = $next($request);
        static::$nextReturnTime = microtime(true);

        static::$handleReturnTime = microtime(true);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        static::$terminateEntryTime = microtime(true);
    }

    /** @return array<int, array{phase: string, start_ms: float, duration_ms: float}>|null */
    public static function getLifecyclePhases(): ?array
    {
        if (static::$handleEntryTime === null || static::$nextReturnTime === null) {
            return null;
        }

        $laravelStart = defined('LARAVEL_START') ? LARAVEL_START : static::$handleEntryTime;

        $toMs = fn (float $seconds): float => round($seconds * 1000, 2);
        $offsetMs = fn (float $timestamp): float => $toMs($timestamp - $laravelStart);

        $phases = [];

        $phases[] = [
            'phase' => 'bootstrap',
            'start_ms' => 0,
            'duration_ms' => $toMs(static::$handleEntryTime - $laravelStart),
        ];

        $phases[] = [
            'phase' => 'middleware',
            'start_ms' => $offsetMs(static::$handleEntryTime),
            'duration_ms' => $toMs(static::$nextCallTime - static::$handleEntryTime),
        ];

        $phases[] = [
            'phase' => 'controller',
            'start_ms' => $offsetMs(static::$nextCallTime),
            'duration_ms' => $toMs(static::$nextReturnTime - static::$nextCallTime),
        ];

        if (static::$handleReturnTime !== null) {
            $phases[] = [
                'phase' => 'middleware_after',
                'start_ms' => $offsetMs(static::$nextReturnTime),
                'duration_ms' => $toMs(static::$handleReturnTime - static::$nextReturnTime),
            ];
        }

        if (static::$terminateEntryTime !== null && static::$handleReturnTime !== null) {
            $phases[] = [
                'phase' => 'sending',
                'start_ms' => $offsetMs(static::$handleReturnTime),
                'duration_ms' => $toMs(static::$terminateEntryTime - static::$handleReturnTime),
            ];

            $terminateEnd = microtime(true);
            $phases[] = [
                'phase' => 'terminating',
                'start_ms' => $offsetMs(static::$terminateEntryTime),
                'duration_ms' => $toMs($terminateEnd - static::$terminateEntryTime),
            ];
        }

        return $phases;
    }

    public static function resetLifecycle(): void
    {
        static::$handleEntryTime = null;
        static::$nextCallTime = null;
        static::$nextReturnTime = null;
        static::$handleReturnTime = null;
        static::$terminateEntryTime = null;
    }

    protected function shouldIgnore(Request $request): bool
    {
        foreach (config('laraspan.ignore_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
