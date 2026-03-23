<?php

use LaraSpan\Client\Transport\HttpSender;

it('can be constructed with parameters', function () {
    $sender = new HttpSender(
        baseUrl: 'http://localhost:8080',
        token: 'test-token',
        timeout: 5,
    );

    expect($sender)->toBeInstanceOf(HttpSender::class);
});

it('has correct SDK version constant', function () {
    // Verify SDK_VERSION is a valid semver string (e.g. "2.1.0")
    expect(HttpSender::SDK_VERSION)->toMatch('/^\d+\.\d+\.\d+$/');
});
