<?php

use Illuminate\Contracts\Auth\Authenticatable;
use LaraSpan\Client\Support\UserProvider;

it('returns null when no auth', function () {
    $provider = new UserProvider;

    expect($provider->resolve())->toBeNull();
});

it('uses custom resolver', function () {
    $provider = new UserProvider;

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockUser->shouldReceive('getAuthIdentifier')->andReturn(42);

    $provider->setResolver(function (Authenticatable $user) {
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => 'Custom Name',
            'email' => 'custom@example.com',
        ];
    });

    $provider->remember($mockUser);
    $result = $provider->getRemembered();

    expect($result)->toBe([
        'id' => 42,
        'name' => 'Custom Name',
        'email' => 'custom@example.com',
    ]);
});

it('remembers user on logout', function () {
    $provider = new UserProvider;

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockUser->shouldReceive('getAuthIdentifier')->andReturn(99);
    $mockUser->name = 'Test User';
    $mockUser->email = 'test@example.com';

    $provider->remember($mockUser);

    $remembered = $provider->getRemembered();

    expect($remembered)->not->toBeNull();
    expect($remembered['id'])->toBe(99);
    expect($remembered['name'])->toBe('Test User');
    expect($remembered['email'])->toBe('test@example.com');
});

it('returns default user fields', function () {
    $provider = new UserProvider;

    $mockUser = Mockery::mock(Authenticatable::class);
    $mockUser->shouldReceive('getAuthIdentifier')->andReturn(1);
    $mockUser->name = 'John Doe';
    $mockUser->email = 'john@example.com';

    // Use remember + getRemembered to test resolveDetails without needing auth()
    $provider->remember($mockUser);
    $result = $provider->getRemembered();

    expect($result)->toBe([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});
