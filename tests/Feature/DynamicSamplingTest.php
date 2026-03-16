<?php

use LaraSpan\Client\LaraSpan;
use LaraSpan\Client\Support\Sampler;

it('dontSample sets override to 0.0', function () {
    $sampler = app(Sampler::class);

    LaraSpan::dontSample();

    // With override 0.0, nothing should sample (except exceptions)
    expect($sampler->shouldSample('request'))->toBeFalse();
    expect($sampler->shouldSample('query'))->toBeFalse();
    expect($sampler->shouldSample('exception'))->toBeTrue(); // always passes
});

it('sample sets override to given rate', function () {
    $sampler = app(Sampler::class);

    LaraSpan::sample(0.0);

    expect($sampler->shouldSample('request'))->toBeFalse();
});

it('override affects sampling decisions', function () {
    $sampler = new Sampler(['request' => 0.0]); // would normally never sample

    $sampler->setOverride(1.0); // override to always sample

    expect($sampler->shouldSample('request'))->toBeTrue();
});
