<?php

namespace LaraSpan\Client\Support;

class LazyValue implements \JsonSerializable
{
    public function __construct(private \Closure $resolver) {}

    public function jsonSerialize(): mixed
    {
        return ($this->resolver)();
    }
}
