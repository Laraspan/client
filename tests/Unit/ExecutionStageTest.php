<?php

use LaraSpan\Client\ExecutionStage;

it('returns all HTTP stages in order', function () {
    $stages = ExecutionStage::forHttp();

    expect($stages)->toHaveCount(7);
    expect($stages)->toBe([
        ExecutionStage::Bootstrap,
        ExecutionStage::BeforeMiddleware,
        ExecutionStage::Action,
        ExecutionStage::Render,
        ExecutionStage::AfterMiddleware,
        ExecutionStage::Sending,
        ExecutionStage::Terminating,
    ]);
});

it('returns command stages', function () {
    $stages = ExecutionStage::forCommand();

    expect($stages)->toBe([
        ExecutionStage::Bootstrap,
        ExecutionStage::Action,
        ExecutionStage::Terminating,
    ]);
});

it('has string backed values', function () {
    expect(ExecutionStage::Bootstrap->value)->toBe('bootstrap');
    expect(ExecutionStage::BeforeMiddleware->value)->toBe('before_middleware');
    expect(ExecutionStage::Action->value)->toBe('action');
    expect(ExecutionStage::Render->value)->toBe('render');
    expect(ExecutionStage::AfterMiddleware->value)->toBe('after_middleware');
    expect(ExecutionStage::Sending->value)->toBe('sending');
    expect(ExecutionStage::Terminating->value)->toBe('terminating');
});
