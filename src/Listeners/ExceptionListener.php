<?php

namespace LaraSpan\Client\Listeners;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\View\ViewException;
use LaraSpan\Client\EventBuffer;
use LaraSpan\Client\Support\ExceptionFingerprinter;
use LaraSpan\Client\Support\SourceCodeCapture;
use Throwable;

class ExceptionListener
{
    private static string $reservedMemory = '';

    public static function reserveMemory(): void
    {
        self::$reservedMemory = str_repeat('x', 32 * 1024); // 32KB
    }

    public function __construct(protected EventBuffer $buffer) {}

    public function handle(MessageLogged $event): void
    {
        try {
            $exception = $event->context['exception'] ?? null;

            // OOM check must come first — free reserved memory so we can still process
            if ($exception instanceof \Error && str_contains($exception->getMessage(), 'Allowed memory size')) {
                self::$reservedMemory = '';
            }

            if ($event->level !== 'error' && $event->level !== 'critical' && $event->level !== 'emergency') {
                return;
            }

            if (! $exception instanceof Throwable) {
                return;
            }

            // Unwrap ViewException to expose the real underlying cause
            if (($exception instanceof ViewException
                || (class_exists(\Spatie\LaravelIgnition\Exceptions\ViewException::class)
                    && $exception instanceof \Spatie\LaravelIgnition\Exceptions\ViewException))
                && $exception->getPrevious() !== null
            ) {
                $exception = $exception->getPrevious();
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

            $payload = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'exception_code' => (int) $exception->getCode(),
                'log_level' => $event->level,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code_snippet' => $exceptionSourceCode,
                'trace' => $trace,
                'route' => $request?->route()?->uri(),
                'uri' => $request?->getRequestUri(),
                'user_id' => $request?->user()?->getAuthIdentifier(),
                'request_id' => $this->buffer->getRequestId(),
            ];

            $previous = $exception->getPrevious();
            if ($previous) {
                $payload['previous_exception'] = [
                    'class' => get_class($previous),
                    'message' => $previous->getMessage(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                ];
            }

            $this->buffer->push([
                'type' => 'exception',
                'fingerprint' => ExceptionFingerprinter::fingerprint($exception),
                'occurred_at' => now()->toIso8601String(),
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
