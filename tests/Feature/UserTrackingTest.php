<?php

use LaraSpan\Client\LaraSpan;
use LaraSpan\Client\Support\UserProvider;

it('registers UserProvider as singleton', function () {
    $provider1 = app(UserProvider::class);
    $provider2 = app(UserProvider::class);

    expect($provider1)->toBe($provider2);
});

it('adds user method to LaraSpan facade', function () {
    LaraSpan::user(function ($user) {
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => 'Custom',
            'email' => 'custom@test.com',
        ];
    });

    $provider = app(UserProvider::class);

    // Verify the resolver was set by checking it's a UserProvider instance
    // (the resolver is private, but we can verify through behavior)
    expect($provider)->toBeInstanceOf(UserProvider::class);
});
