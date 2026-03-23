<?php

use LaraSpan\Client\Support\LazyValue;
use LaraSpan\Client\Support\Redactor;

it('redacts matching keys', function () {
    $redactor = new Redactor(['password', 'secret']);

    $result = $redactor->redact([
        'username' => 'john',
        'password' => 'hunter2',
        'secret' => 'abc123',
    ]);

    expect($result)
        ->username->toBe('john')
        ->password->toBe('[REDACTED]')
        ->secret->toBe('[REDACTED]');
});

it('redacts nested keys', function () {
    $redactor = new Redactor(['password']);

    $result = $redactor->redact([
        'user' => [
            'name' => 'john',
            'password' => 'hunter2',
        ],
    ]);

    expect($result['user']['password'])->toBe('[REDACTED]');
    expect($result['user']['name'])->toBe('john');
});

it('is case-insensitive', function () {
    $redactor = new Redactor(['password']);

    $result = $redactor->redact([
        'Password' => 'hunter2',
        'PASSWORD' => 'hunter3',
    ]);

    expect($result['Password'])->toBe('[REDACTED]');
    expect($result['PASSWORD'])->toBe('[REDACTED]');
});

it('does not redact non-matching keys', function () {
    $redactor = new Redactor(['password']);

    $result = $redactor->redact([
        'username' => 'john',
        'email' => 'john@test.com',
    ]);

    expect($result)
        ->username->toBe('john')
        ->email->toBe('john@test.com');
});

it('handles empty data', function () {
    $redactor = new Redactor(['password']);

    expect($redactor->redact([]))->toBe([]);
});

it('resolves JsonSerializable objects before redacting', function () {
    $redactor = new Redactor(['password']);
    $lazy = new LazyValue(fn () => ['password' => 'secret', 'name' => 'john']);

    $result = $redactor->redact(['data' => $lazy]);

    expect($result['data']['password'])->toBe('[REDACTED]');
    expect($result['data']['name'])->toBe('john');
});
