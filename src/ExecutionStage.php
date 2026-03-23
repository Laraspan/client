<?php

namespace LaraSpan\Client;

enum ExecutionStage: string
{
    case Bootstrap = 'bootstrap';
    case BeforeMiddleware = 'before_middleware';
    case Action = 'action';
    case Render = 'render';
    case AfterMiddleware = 'after_middleware';
    case Sending = 'sending';
    case Terminating = 'terminating';

    public static function forHttp(): array
    {
        return self::cases();
    }

    public static function forCommand(): array
    {
        return [
            self::Bootstrap,
            self::Action,
            self::Terminating,
        ];
    }
}
