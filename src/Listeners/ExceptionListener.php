<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Log\Events\MessageLogged;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Support\ExceptionFingerprinter;
use LaraSpan\Client\Support\SourceCodeCapture;
use Throwable;

class ExceptionListener
{
    public function __construct(protected EventBuffer $buffer) {}

    public function handle(MessageLogged $event): void
    {
        if ($event->level !== 'error' && $event->level !== 'critical' && $event->level !== 'emergency') {
            return;
        }

        $exception = $event->context['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return;
        }

        $ignoreList = config('laraspan.ignore_exceptions', []);

        foreach ($ignoreList as $ignoredClass) {
            if ($exception instanceof $ignoredClass) {
                return;
            }
        }

        $captureSourceCode = config('laraspan.capture.source_code', true);

        $trace = collect($exception->getTrace())
            ->take(50)
            ->map(function (array $frame) use ($captureSourceCode) {
                $isAppFrame = isset($frame['file']) && str_contains($frame['file'], base_path('app'));

                $mapped = [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'is_app_frame' => $isAppFrame,
                ];

                if ($captureSourceCode && $isAppFrame && isset($frame['file'], $frame['line'])) {
                    $mapped['code_snippet'] = SourceCodeCapture::capture($frame['file'], $frame['line']);
                }

                return $mapped;
            })
            ->all();

        // Capture source code for the exception origin (file + line on the exception itself)
        $exceptionSourceCode = null;
        if ($captureSourceCode) {
            $exceptionSourceCode = SourceCodeCapture::capture($exception->getFile(), $exception->getLine());
        }

        $request = request();

        $this->buffer->push([
            'type' => 'exception',
            'fingerprint' => ExceptionFingerprinter::fingerprint($exception),
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code_snippet' => $exceptionSourceCode,
                'trace' => $trace,
                'route' => $request?->route()?->uri(),
                'request_id' => $this->buffer->getRequestId(),
            ],
        ]);
    }
}
