<?php

namespace LaraSpan\Client\Support;

class SqlNormalizer
{
    public static function normalize(string $sql): string
    {
        // Replace quoted strings with placeholder
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        // Replace numeric literals (integers and floats) with placeholder
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);

        // Collapse multiple whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
    }
}
