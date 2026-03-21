<?php

namespace LaraSpan\Client\Support;

class SqlNormalizer
{
    public static function normalize(string $sql): string
    {
        // Replace quoted strings with placeholder
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        // Replace numeric literals (integers and floats) with placeholder
        $sql = preg_replace('/(?<![a-zA-Z_])\b\d+(\.\d+)?\b(?![a-zA-Z_])/', '?', $sql);

        // Collapse multiple whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
    }
}
