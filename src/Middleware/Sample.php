<?php

namespace LaraSpan\Client\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Sample
{
    public function __construct(protected float $rate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('laraspan_sample_rate', $this->rate);

        return $next($request);
    }

    public static function rate(float $rate): string
    {
        return static::class.':'.$rate;
    }

    public static function always(): string
    {
        return static::class.':1.0';
    }

    public static function never(): string
    {
        return static::class.':0.0';
    }
}
