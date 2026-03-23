<?php

namespace LaraSpan\Client\Support;

class Redactor
{
    /** @var array<int, string> */
    protected array $keys;

    /** @param array<int, string> $keys */
    public function __construct(array $keys = [])
    {
        $this->keys = array_map('strtolower', $keys);
    }

    /** @param array<string, mixed> $data */
    public function redact(array $data): array
    {
        return $this->walk($data);
    }

    protected function walk(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->keys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->walk($value);
            } elseif ($value instanceof \JsonSerializable) {
                $resolved = $value->jsonSerialize();
                $data[$key] = is_array($resolved) ? $this->walk($resolved) : $resolved;
            }
        }

        return $data;
    }
}
