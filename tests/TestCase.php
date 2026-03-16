<?php

namespace LaraSpan\Client\Tests;

use LaraSpan\Client\LaraSpanServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaraSpanServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('laraspan.enabled', true);
        $app['config']->set('laraspan.token', 'test-token');
        $app['config']->set('laraspan.endpoint', 'http://localhost:8080/api/ingest');
        $app['config']->set('laraspan.transport', 'inline');
    }
}
