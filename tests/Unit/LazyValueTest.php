<?php

use LaraSpan\Client\Support\LazyValue;

it('resolves callback on jsonSerialize', function () {
    $lazy = new LazyValue(fn () => 42);

    expect($lazy->jsonSerialize())->toBe(42);
});

it('defers execution until serialization', function () {
    $called = false;

    $lazy = new LazyValue(function () use (&$called) {
        $called = true;

        return 'resolved';
    });

    expect($called)->toBeFalse();

    $lazy->jsonSerialize();

    expect($called)->toBeTrue();
});

it('works with json_encode', function () {
    $result = json_encode(['val' => new LazyValue(fn () => 42)]);

    expect($result)->toBe('{"val":42}');
});

it('resolves to arrays', function () {
    $lazy = new LazyValue(fn () => ['a', 'b']);

    expect($lazy->jsonSerialize())->toBe(['a', 'b']);
});

it('resolves to strings', function () {
    $lazy = new LazyValue(fn () => 'hello');

    expect($lazy->jsonSerialize())->toBe('hello');
});

it('resolves to null', function () {
    $lazy = new LazyValue(fn () => null);

    expect($lazy->jsonSerialize())->toBeNull();
});
