<?php

namespace LaraSpan\Client\Support;

class PerformanceFingerprinter
{
    public static function request(string $route): string
    {
        return sha1('perf:request:'.$route);
    }

    public static function job(string $jobClass): string
    {
        return sha1('perf:job:'.$jobClass);
    }

    public static function query(string $sql): string
    {
        return sha1('perf:query:'.SqlNormalizer::normalize($sql));
    }
}
