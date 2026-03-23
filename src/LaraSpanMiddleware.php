<?php

namespace LaraSpan\Client;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LaraSpanMiddleware
{
    public function __construct(
        protected ExecutionState $state,
        protected EventBuffer $buffer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->state->transitionTo(ExecutionStage::BeforeMiddleware);

            if ($this->shouldIgnore($request)) {
                $this->state->pause();
            }

            $this->state->transitionTo(ExecutionStage::Action);
            $response = $next($request);
            $this->state->transitionTo(ExecutionStage::Render);

            $this->state->transitionTo(ExecutionStage::AfterMiddleware);

            return $response;
        } catch (\Throwable $e) {
            report($e);

            return $next($request);
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->state->transitionTo(ExecutionStage::Terminating);
        } catch (\Throwable $e) {
            report($e);
        }
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
