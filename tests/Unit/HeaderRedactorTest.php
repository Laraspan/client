<?php

use LaraSpan\Client\Support\HeaderRedactor;

it('passes through non-sensitive headers unchanged', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Content-Type' => 'application/json',
        'Accept' => 'text/html',
    ]);

    expect($result)
        ->{'Content-Type'}->toBe('application/json')
        ->Accept->toBe('text/html');
});

it('redacts authorization header preserving scheme', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Authorization' => 'Bearer abc123',
    ]);

    expect($result['Authorization'])->toBe('Bearer [6 bytes redacted]');
});

it('redacts proxy-authorization header preserving scheme', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Proxy-Authorization' => 'Bearer abc123',
    ]);

    expect($result['Proxy-Authorization'])->toBe('Bearer [6 bytes redacted]');
});

it('redacts authorization without recognized scheme', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Authorization' => 'custom-token',
    ]);

    expect($result['Authorization'])->toBe('[12 bytes redacted]');
});

it('redacts basic auth preserving scheme', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Authorization' => 'Basic dXNlcjpwYXNz',
    ]);

    expect($result['Authorization'])->toBe('Basic [12 bytes redacted]');
});

it('redacts cookie header preserving names', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Cookie' => 'session_id=abc123; theme=dark',
    ]);

    expect($result['Cookie'])->toBe('session_id=[6 bytes redacted]; theme=[4 bytes redacted]');
});

it('redacts set-cookie header preserving names', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Set-Cookie' => 'session_id=abc123; theme=dark',
    ]);

    expect($result['Set-Cookie'])->toBe('session_id=[6 bytes redacted]; theme=[4 bytes redacted]');
});

it('redacts x-csrf-token fully', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'X-CSRF-TOKEN' => 'abc',
    ]);

    expect($result['X-CSRF-TOKEN'])->toBe('[3 bytes redacted]');
});

it('redacts x-xsrf-token fully', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'X-XSRF-TOKEN' => 'abc',
    ]);

    expect($result['X-XSRF-TOKEN'])->toBe('[3 bytes redacted]');
});

it('is case-insensitive for header names', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'AUTHORIZATION' => 'Bearer token1',
        'authorization' => 'Bearer token2',
    ]);

    expect($result['AUTHORIZATION'])->toBe('Bearer [6 bytes redacted]');
    expect($result['authorization'])->toBe('Bearer [6 bytes redacted]');
});

it('handles additional custom sensitive headers', function () {
    $redactor = new HeaderRedactor(['X-Api-Key']);

    $result = $redactor->redact([
        'X-Api-Key' => 'my-secret-key',
    ]);

    expect($result['X-Api-Key'])->toBe('[13 bytes redacted]');
});

it('handles array header values', function () {
    $redactor = new HeaderRedactor(['x-custom-secret']);

    $result = $redactor->redact([
        'X-Custom-Secret' => ['value1', 'value2'],
    ]);

    // Array values are joined with ', ' then fully redacted
    expect($result['X-Custom-Secret'])->toBe('[14 bytes redacted]');
});

it('handles cookie without equals sign gracefully', function () {
    $redactor = new HeaderRedactor;

    $result = $redactor->redact([
        'Cookie' => 'malformed-cookie-no-equals',
    ]);

    expect($result['Cookie'])->toBe('[26 bytes redacted]');
});
