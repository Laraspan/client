<?php

use LaraSpan\Client\Support\PerformanceFingerprinter;

it('produces deterministic request fingerprints', function () {
    $fp1 = PerformanceFingerprinter::request('api/users');
    $fp2 = PerformanceFingerprinter::request('api/users');

    expect($fp1)->toBe($fp2);
});

it('produces different fingerprints for different routes', function () {
    $fp1 = PerformanceFingerprinter::request('api/users');
    $fp2 = PerformanceFingerprinter::request('api/posts');

    expect($fp1)->not->toBe($fp2);
});

it('produces deterministic job fingerprints', function () {
    $fp1 = PerformanceFingerprinter::job('App\\Jobs\\SendEmail');
    $fp2 = PerformanceFingerprinter::job('App\\Jobs\\SendEmail');

    expect($fp1)->toBe($fp2);
});

it('produces different fingerprints for different job classes', function () {
    $fp1 = PerformanceFingerprinter::job('App\\Jobs\\SendEmail');
    $fp2 = PerformanceFingerprinter::job('App\\Jobs\\ProcessPayment');

    expect($fp1)->not->toBe($fp2);
});

it('produces deterministic query fingerprints', function () {
    $fp1 = PerformanceFingerprinter::query('select * from users where id = 42');
    $fp2 = PerformanceFingerprinter::query('select * from users where id = 999');

    expect($fp1)->toBe($fp2);
});

it('produces different fingerprints for different queries', function () {
    $fp1 = PerformanceFingerprinter::query('select * from users where id = 1');
    $fp2 = PerformanceFingerprinter::query('select * from posts where id = 1');

    expect($fp1)->not->toBe($fp2);
});

it('produces different fingerprints across types', function () {
    $route = PerformanceFingerprinter::request('api/users');
    $job = PerformanceFingerprinter::job('api/users');
    $query = PerformanceFingerprinter::query('api/users');

    expect($route)->not->toBe($job);
    expect($route)->not->toBe($query);
    expect($job)->not->toBe($query);
});

it('returns sha1 hashes', function () {
    expect(PerformanceFingerprinter::request('test'))->toMatch('/^[a-f0-9]{40}$/');
    expect(PerformanceFingerprinter::job('test'))->toMatch('/^[a-f0-9]{40}$/');
    expect(PerformanceFingerprinter::query('test'))->toMatch('/^[a-f0-9]{40}$/');
});
