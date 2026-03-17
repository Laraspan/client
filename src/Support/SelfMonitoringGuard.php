<?php

namespace LaraSpan\Client\Support;

class SelfMonitoringGuard
{
    public function __construct(
        protected string $laraSpanUrl,
        protected string $appUrl,
    ) {}

    public function isSelfRequest(): bool
    {
        if (! $this->isSelfMonitoring()) {
            return false;
        }

        $request = request();

        return $request && $request->is('api/ingest', 'api/deploy');
    }

    public function isSelfMonitoring(): bool
    {
        return $this->laraSpanUrl !== ''
            && $this->appUrl !== ''
            && $this->laraSpanUrl === $this->appUrl;
    }
}
