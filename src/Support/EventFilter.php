<?php

namespace LaraSpan\Client\Support;

use Closure;

class EventFilter
{
    /** @var array<string, Closure> */
    protected array $rejectors = [];

    public function rejectQueries(Closure $callback): self
    {
        $this->rejectors['query'] = $callback;

        return $this;
    }

    public function rejectJobs(Closure $callback): self
    {
        $this->rejectors['job'] = $callback;

        return $this;
    }

    public function rejectCacheKeys(Closure $callback): self
    {
        $this->rejectors['cache'] = $callback;

        return $this;
    }

    public function rejectMail(Closure $callback): self
    {
        $this->rejectors['mail'] = $callback;

        return $this;
    }

    public function rejectNotifications(Closure $callback): self
    {
        $this->rejectors['notification'] = $callback;

        return $this;
    }

    public function rejectLogs(Closure $callback): self
    {
        $this->rejectors['log'] = $callback;

        return $this;
    }

    public function rejectHttpClient(Closure $callback): self
    {
        $this->rejectors['http_client'] = $callback;

        return $this;
    }

    public function rejectCommands(Closure $callback): self
    {
        $this->rejectors['command'] = $callback;

        return $this;
    }

    /** @param array<string, mixed> $event */
    public function shouldReject(array $event): bool
    {
        $type = $event['type'] ?? null;

        if (! $type || ! isset($this->rejectors[$type])) {
            return false;
        }

        return (bool) ($this->rejectors[$type])($event['payload'] ?? []);
    }
}
