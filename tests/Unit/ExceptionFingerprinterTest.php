<?php

use LaraSpan\Client\Support\ExceptionFingerprinter;

it('produces deterministic fingerprints', function () {
    $e = new RuntimeException('User 42 not found', 0);

    $fp1 = ExceptionFingerprinter::fingerprint($e);
    $fp2 = ExceptionFingerprinter::fingerprint($e);

    expect($fp1)->toBe($fp2);
});

it('produces same fingerprint regardless of dynamic integer values', function () {
    // Create both on the same line so file+line match
    [$e1, $e2] = [new RuntimeException('User 42 not found'), new RuntimeException('User 999 not found')];

    expect(ExceptionFingerprinter::fingerprint($e1))
        ->toBe(ExceptionFingerprinter::fingerprint($e2));
});

it('produces different fingerprints for different exception classes', function () {
    $e1 = new RuntimeException('Something failed');
    $e2 = new InvalidArgumentException('Something failed');

    expect(ExceptionFingerprinter::fingerprint($e1))
        ->not->toBe(ExceptionFingerprinter::fingerprint($e2));
});

it('returns a sha1 hash', function () {
    $fp = ExceptionFingerprinter::fingerprint(new RuntimeException('test'));

    expect($fp)->toMatch('/^[a-f0-9]{40}$/');
});
