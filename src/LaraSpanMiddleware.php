<?php

namespace LaraSpan\Client;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LaraSpanMiddleware
{
    public function __construct(protected EventBuffer $buffer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldIgnore($request)) {
            $this->buffer->pause();

            return $next($request);
        }

        $request->attributes->set('laraspan_start_time', microtime(true));
        $request->attributes->set('laraspan_request_id', $this->buffer->getRequestId());

        $this->buffer->setContext([
            'request_id' => $this->buffer->getRequestId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return $next($request);
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
