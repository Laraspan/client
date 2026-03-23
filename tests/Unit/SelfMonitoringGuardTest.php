<?php

use Illuminate\Http\Request;
use LaraSpan\Client\Support\SelfMonitoringGuard;
use LaraSpan\Client\Tests\TestCase;

uses(TestCase::class);

it('detects self-monitoring when URLs match', function () {
    $guard = new SelfMonitoringGuard('http://localhost:8080', 'http://localhost:8080');

    expect($guard->isSelfMonitoring())->toBeTrue();
});

it('does not detect self-monitoring when URLs differ', function () {
    $guard = new SelfMonitoringGuard('http://laraspan.test', 'http://myapp.test');

    expect($guard->isSelfMonitoring())->toBeFalse();
});

it('detects self-request for ingest endpoint', function () {
    $guard = new SelfMonitoringGuard('http://localhost:8080', 'http://localhost:8080');

    // Simulate a request to api/v1/ingest
    $request = Request::create('http://localhost:8080/api/v1/ingest', 'POST');
    app()->instance('request', $request);

    expect($guard->isSelfRequest())->toBeTrue();
});

it('detects self-request for deploy endpoint', function () {
    $guard = new SelfMonitoringGuard('http://localhost:8080', 'http://localhost:8080');

    $request = Request::create('http://localhost:8080/api/v1/deploy', 'POST');
    app()->instance('request', $request);

    expect($guard->isSelfRequest())->toBeTrue();
});

it('does not flag non-ingest requests as self-request', function () {
    $guard = new SelfMonitoringGuard('http://localhost:8080', 'http://localhost:8080');

    $request = Request::create('http://localhost:8080/dashboard', 'GET');
    app()->instance('request', $request);

    expect($guard->isSelfRequest())->toBeFalse();
});

it('handles empty URLs', function () {
    $guard = new SelfMonitoringGuard('', 'http://myapp.test');
    expect($guard->isSelfMonitoring())->toBeFalse();

    $guard = new SelfMonitoringGuard('http://laraspan.test', '');
    expect($guard->isSelfMonitoring())->toBeFalse();

    $guard = new SelfMonitoringGuard('', '');
    expect($guard->isSelfMonitoring())->toBeFalse();
});

it('handles trailing slashes as exact comparison', function () {
    // The guard uses strict equality — trailing slash causes mismatch
    $guard = new SelfMonitoringGuard('http://localhost:8080/', 'http://localhost:8080');
    expect($guard->isSelfMonitoring())->toBeFalse();

    $guard = new SelfMonitoringGuard('http://localhost:8080/', 'http://localhost:8080/');
    expect($guard->isSelfMonitoring())->toBeTrue();
});
