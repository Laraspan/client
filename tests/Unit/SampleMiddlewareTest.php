<?php

use LaraSpan\Client\Middleware\Sample;

it('generates rate middleware string', function () {
    expect(Sample::rate(0.5))->toBe(Sample::class.':0.5');
});

it('generates always middleware string', function () {
    expect(Sample::always())->toBe(Sample::class.':1.0');
});

it('generates never middleware string', function () {
    expect(Sample::never())->toBe(Sample::class.':0.0');
});
