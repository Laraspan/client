<?php

use LaraSpan\Client\Support\Sampler;

it('always samples exceptions', function () {
    $sampler = new Sampler(['exception' => 0.0, 'request' => 0.0]);

    // Even with 0.0 rate, exceptions should pass
    expect($sampler->shouldSample('exception'))->toBeTrue();
});

it('always samples when rate is 1.0', function () {
    $sampler = new Sampler(['request' => 1.0]);

    // Run multiple times to be sure
    for ($i = 0; $i < 20; $i++) {
        expect($sampler->shouldSample('request'))->toBeTrue();
    }
});

it('never samples when rate is 0.0', function () {
    $sampler = new Sampler(['request' => 0.0]);

    for ($i = 0; $i < 20; $i++) {
        expect($sampler->shouldSample('request'))->toBeFalse();
    }
});

it('defaults to 1.0 for unknown event types', function () {
    $sampler = new Sampler([]);

    expect($sampler->shouldSample('some_unknown_type'))->toBeTrue();
});
