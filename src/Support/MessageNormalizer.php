<?php

namespace LaraSpan\Client\Support;

class MessageNormalizer
{
    public static function normalize(string $message): string
    {
        // Replace UUIDs
        $message = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '[uuid]',
            $message
        );

        // Replace email addresses
        $message = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[email]',
            $message
        );

        // Replace integers (standalone numbers)
        $message = preg_replace('/\b\d+\b/', '[int]', $message);

        return $message;
    }
}
