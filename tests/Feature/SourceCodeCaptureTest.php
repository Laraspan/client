<?php

use LaraSpan\Client\Support\SourceCodeCapture;

it('captures source code around the given line', function () {
    // Create a temp file within base_path
    $file = base_path('app/test-source-capture.php');
    $lines = [];
    for ($i = 1; $i <= 20; $i++) {
        $lines[] = "// Line {$i}";
    }
    file_put_contents($file, implode("\n", $lines));

    $result = SourceCodeCapture::capture($file, 10, 3);

    expect($result)->not->toBeNull();
    expect($result['start_line'])->toBe(7);
    expect($result['highlight_line'])->toBe(10);
    expect($result['code'])->toHaveCount(7); // 3 before + target + 3 after

    unlink($file);
});

it('returns null for non-existent files', function () {
    $result = SourceCodeCapture::capture('/nonexistent/file.php', 10);

    expect($result)->toBeNull();
});

it('handles lines near the beginning of file', function () {
    $file = base_path('app/test-source-capture-begin.php');
    $lines = [];
    for ($i = 1; $i <= 10; $i++) {
        $lines[] = "// Line {$i}";
    }
    file_put_contents($file, implode("\n", $lines));

    $result = SourceCodeCapture::capture($file, 2, 5);

    expect($result)->not->toBeNull();
    expect($result['start_line'])->toBe(1);
    expect($result['highlight_line'])->toBe(2);

    unlink($file);
});

it('handles lines near the end of file', function () {
    $file = base_path('app/test-source-capture-end.php');
    $lines = [];
    for ($i = 1; $i <= 10; $i++) {
        $lines[] = "// Line {$i}";
    }
    file_put_contents($file, implode("\n", $lines));

    $result = SourceCodeCapture::capture($file, 9, 5);

    expect($result)->not->toBeNull();
    expect($result['highlight_line'])->toBe(9);
    // Should end at line 10 (last line)
    expect($result['start_line'] + count($result['code']) - 1)->toBeLessThanOrEqual(10);

    unlink($file);
});

it('returns null for files outside base path', function () {
    $result = SourceCodeCapture::capture('/tmp/outside-file.php', 10);

    expect($result)->toBeNull();
});
