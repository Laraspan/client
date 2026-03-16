<?php

namespace LaraSpan\Client\Support;

use Throwable;

class ExceptionFingerprinter
{
    public static function fingerprint(Throwable $e): string
    {
        $normalizedMessage = MessageNormalizer::normalize($e->getMessage());

        return sha1(
            get_class($e).$e->getFile().$e->getLine().$normalizedMessage
        );
    }
}
