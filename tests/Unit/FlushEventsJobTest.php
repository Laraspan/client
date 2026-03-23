<?php

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use LaraSpan\Client\Jobs\FlushEventsJob;

it('is a queued job', function () {
    $job = new FlushEventsJob;

    expect($job)->toBeInstanceOf(ShouldQueue::class);
});

it('is unique', function () {
    $job = new FlushEventsJob;

    expect($job)->toBeInstanceOf(ShouldBeUnique::class);
});

it('has retry configuration', function () {
    $job = new FlushEventsJob;

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([5, 30, 60]);
});

it('has unique timeout', function () {
    $job = new FlushEventsJob;

    expect($job->uniqueFor)->toBe(10);
});
