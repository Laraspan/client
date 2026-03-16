<?php

it('runs the install command', function () {
    $this->artisan('laraspan:install')
        ->assertSuccessful();
});

it('runs the test command and fails without a reachable server', function () {
    config()->set('laraspan.token', 'test-token');
    config()->set('laraspan.url', 'http://localhost:9999');

    $this->artisan('laraspan:test')
        ->assertFailed();
});

it('fails test command without token', function () {
    config()->set('laraspan.token', '');

    $this->artisan('laraspan:test')
        ->assertFailed();
});
