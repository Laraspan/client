<?php

namespace LaraSpan\Client\Support;

use RuntimeException;
use Throwable;

class HeaderRedactor
{
    /** @var list<string> */
    private array $sensitiveHeaders;

    /** @var list<string> */
    private const BUILT_IN_SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /** @param list<string> $additionalHeaders */
    public function __construct(array $additionalHeaders = [])
    {
        $this->sensitiveHeaders = array_merge(
            self::BUILT_IN_SENSITIVE_HEADERS,
            array_map('strtolower', $additionalHeaders),
        );
    }

    /**
     * Redact sensitive header values with scheme-aware logic.
     *
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>
     */
    public function redact(array $headers): array
    {
        $result = [];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);

            if (! in_array($lowerKey, $this->sensitiveHeaders, true)) {
                $result[$key] = is_array($values) ? implode(', ', $values) : $values;

                continue;
            }

            $flatValue = is_array($values) ? implode(', ', $values) : $values;

            $result[$key] = match ($lowerKey) {
                'authorization', 'proxy-authorization' => $this->redactAuthorizationValue($flatValue),
                'cookie', 'set-cookie' => $this->redactCookieValue($flatValue),
                default => $this->redactValue($flatValue),
            };
        }

        return $result;
    }

    private function redactValue(string $value): string
    {
        return '['.strlen($value).' bytes redacted]';
    }

    private function redactAuthorizationValue(string $value): string
    {
        if (! str_contains($value, ' ')) {
            return $this->redactValue($value);
        }

        [$type, $remainder] = explode(' ', $value, 2);

        if (in_array(strtolower($type), [
            'basic',
            'bearer',
            'concealed',
            'digest',
            'dpop',
            'gnap',
            'hoba',
            'mutual',
            'negotiate',
            'oauth',
            'privatetoken',
            'scram-sha-1',
            'scram-sha-256',
            'vapid',
        ], true)) {
            return $type.' '.$this->redactValue($remainder);
        }

        return $this->redactValue($value);
    }

    private function redactCookieValue(string $value): string
    {
        $cookies = explode(';', $value);

        try {
            return implode('; ', array_map(function ($cookie) {
                if (! str_contains($cookie, '=')) {
                    throw new RuntimeException('Invalid cookie format.');
                }

                [$name, $cookieValue] = explode('=', $cookie, 2);

                return trim($name).'='.$this->redactValue($cookieValue);
            }, $cookies));
        } catch (Throwable) {
            return $this->redactValue($value);
        }
    }
}
