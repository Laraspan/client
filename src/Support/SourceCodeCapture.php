<?php

namespace LaraSpan\Client\Support;

class SourceCodeCapture
{
    /**
     * Capture source code lines around the given line number.
     *
     * @return array{start_line: int, highlight_line: int, code: string[]}|null
     */
    public static function capture(string $file, int $line, int $context = 5): ?array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        // Only capture files within the application directory
        if (! str_starts_with($file, base_path())) {
            return null;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false || empty($lines)) {
            return null;
        }

        $totalLines = count($lines);
        $startLine = max(1, $line - $context);
        $endLine = min($totalLines, $line + $context);

        $code = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $code[] = $lines[$i - 1]; // file() is 0-indexed, lines are 1-indexed
        }

        return [
            'start_line' => $startLine,
            'highlight_line' => $line,
            'code' => $code,
        ];
    }
}
