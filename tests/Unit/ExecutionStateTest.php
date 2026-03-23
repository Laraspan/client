<?php

use LaraSpan\Client\ExecutionStage;
use LaraSpan\Client\ExecutionState;

it('generates a UUID request id', function () {
    $state = new ExecutionState;

    expect($state->getRequestId())
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('sets start time on construction', function () {
    $before = microtime(true);
    $state = new ExecutionState;
    $after = microtime(true);

    expect($state->getStartTime())
        ->toBeGreaterThanOrEqual($before)
        ->toBeLessThanOrEqual($after);
});

it('starts unpaused', function () {
    $state = new ExecutionState;

    expect($state->isPaused())->toBeFalse();
});

it('can pause and resume', function () {
    $state = new ExecutionState;

    $state->pause();
    expect($state->isPaused())->toBeTrue();

    $state->resume();
    expect($state->isPaused())->toBeFalse();
});

it('starts at bootstrap stage', function () {
    $state = new ExecutionState;

    expect($state->getCurrentStage())->toBe(ExecutionStage::Bootstrap);
});

it('tracks stage transitions', function () {
    $state = new ExecutionState;

    $state->transitionTo(ExecutionStage::Action);

    expect($state->getCurrentStage())->toBe(ExecutionStage::Action);
});

it('accumulates stage durations', function () {
    $state = new ExecutionState;

    $state->transitionTo(ExecutionStage::BeforeMiddleware);
    usleep(1000);
    $state->transitionTo(ExecutionStage::Action);

    $durations = $state->getStageDurations();

    expect($durations['before_middleware'])->toBeGreaterThan(0);
});

it('returns lifecycle phases', function () {
    $state = new ExecutionState;

    $state->transitionTo(ExecutionStage::BeforeMiddleware);
    usleep(500);
    $state->transitionTo(ExecutionStage::Action);
    usleep(500);
    $state->transitionTo(ExecutionStage::Render);

    $phases = $state->getLifecyclePhases();

    expect($phases)->toBeArray();
    expect($phases)->not->toBeEmpty();

    foreach ($phases as $phase) {
        expect($phase)->toHaveKeys(['phase', 'start_ms', 'duration_ms']);
    }
});

it('returns null lifecycle phases when no transitions', function () {
    $state = new ExecutionState;

    expect($state->getLifecyclePhases())->toBeNull();
});

it('tracks pending mail', function () {
    $state = new ExecutionState;

    $state->trackPendingMail('mail-key', 1.0);

    expect($state->popPendingMail('mail-key'))->toBe(1.0);
    expect($state->popPendingMail('mail-key'))->toBeNull();
});

it('tracks pending notifications', function () {
    $state = new ExecutionState;

    $state->trackPendingNotification('notif-key', 2.5);

    expect($state->popPendingNotification('notif-key'))->toBe(2.5);
    expect($state->popPendingNotification('notif-key'))->toBeNull();
});

it('tracks pending http requests', function () {
    $state = new ExecutionState;

    $state->trackPendingHttpRequest('http-key', 3.0);

    expect($state->popPendingHttpRequest('http-key'))->toBe(3.0);
    expect($state->popPendingHttpRequest('http-key'))->toBeNull();
});

it('resets all state', function () {
    $state = new ExecutionState;
    $originalId = $state->getRequestId();

    $state->pause();
    $state->transitionTo(ExecutionStage::Action);
    $state->trackPendingMail('m', 1.0);
    $state->trackPendingNotification('n', 2.0);
    $state->trackPendingHttpRequest('h', 3.0);

    $state->reset();

    expect($state->getRequestId())->not->toBe($originalId);
    expect($state->isPaused())->toBeFalse();
    expect($state->getCurrentStage())->toBe(ExecutionStage::Bootstrap);
    expect($state->popPendingMail('m'))->toBeNull();
    expect($state->popPendingNotification('n'))->toBeNull();
    expect($state->popPendingHttpRequest('h'))->toBeNull();

    $durations = $state->getStageDurations();
    foreach ($durations as $duration) {
        expect($duration)->toBe(0.0);
    }
});

it('generates different request id on reset', function () {
    $state = new ExecutionState;
    $originalId = $state->getRequestId();

    $state->reset();

    expect($state->getRequestId())->not->toBe($originalId);
});
