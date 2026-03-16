<?php

use LaraSpan\Client\Support\EventFilter;

it('passes events with no rejectors', function () {
    $filter = new EventFilter;

    expect($filter->shouldReject(['type' => 'query', 'payload' => ['sql' => 'select 1']]))->toBeFalse();
});

it('rejects queries matching callback', function () {
    $filter = new EventFilter;
    $filter->rejectQueries(fn (array $payload) => str_contains($payload['sql'] ?? '', 'telescope'));

    expect($filter->shouldReject(['type' => 'query', 'payload' => ['sql' => 'select * from telescope_entries']]))->toBeTrue();
    expect($filter->shouldReject(['type' => 'query', 'payload' => ['sql' => 'select * from users']]))->toBeFalse();
});

it('rejects jobs matching callback', function () {
    $filter = new EventFilter;
    $filter->rejectJobs(fn (array $payload) => ($payload['job_class'] ?? '') === 'App\\Jobs\\PruneOldRecords');

    expect($filter->shouldReject(['type' => 'job', 'payload' => ['job_class' => 'App\\Jobs\\PruneOldRecords']]))->toBeTrue();
    expect($filter->shouldReject(['type' => 'job', 'payload' => ['job_class' => 'App\\Jobs\\SendEmail']]))->toBeFalse();
});

it('rejects cache keys matching callback', function () {
    $filter = new EventFilter;
    $filter->rejectCacheKeys(fn (array $payload) => str_starts_with($payload['key'] ?? '', 'framework'));

    expect($filter->shouldReject(['type' => 'cache', 'payload' => ['key' => 'framework:cache:data']]))->toBeTrue();
    expect($filter->shouldReject(['type' => 'cache', 'payload' => ['key' => 'users:1']]))->toBeFalse();
});

it('does not apply rejectors to other event types', function () {
    $filter = new EventFilter;
    $filter->rejectQueries(fn () => true);

    // Should not reject a request event even though query rejector returns true
    expect($filter->shouldReject(['type' => 'request', 'payload' => []]))->toBeFalse();
});

it('supports chaining', function () {
    $filter = new EventFilter;

    $result = $filter
        ->rejectQueries(fn () => false)
        ->rejectJobs(fn () => false)
        ->rejectCacheKeys(fn () => false);

    expect($result)->toBeInstanceOf(EventFilter::class);
});
