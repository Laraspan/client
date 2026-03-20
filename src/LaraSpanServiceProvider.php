<?php

namespace LaraSpan\Client;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LaraSpan\Client\Console\Commands\DeployCommand;
use LaraSpan\Client\Console\Commands\FlushCommand;
use LaraSpan\Client\Console\Commands\InstallCommand;
use LaraSpan\Client\Console\Commands\TestCommand;
use LaraSpan\Client\Jobs\FlushEventsJob;
use LaraSpan\Client\Listeners\CacheListener;
use LaraSpan\Client\Listeners\CommandListener;
use LaraSpan\Client\Listeners\ExceptionListener;
use LaraSpan\Client\Listeners\HttpClientListener;
use LaraSpan\Client\Listeners\JobListener;
use LaraSpan\Client\Listeners\LogListener;
use LaraSpan\Client\Listeners\MailListener;
use LaraSpan\Client\Listeners\NotificationListener;
use LaraSpan\Client\Listeners\QueryListener;
use LaraSpan\Client\Listeners\RequestListener;
use LaraSpan\Client\Listeners\SchedulerListener;
use LaraSpan\Client\Support\EventFilter;
use LaraSpan\Client\Support\Redactor;
use LaraSpan\Client\Support\Sampler;
use LaraSpan\Client\Support\SelfMonitoringGuard;
use LaraSpan\Client\Transport\InlineTransport;
use LaraSpan\Client\Transport\QueueTransport;
use LaraSpan\Client\Transport\TransportInterface;
use Laravel\Octane\Events\RequestReceived;

class LaraSpanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraspan.php', 'laraspan');

        $this->app->singleton(EventBuffer::class, function ($app) {
            return new EventBuffer(
                $app['config']->get('laraspan.buffer.max_events_per_request', 5000)
            );
        });

        $this->app->singleton(TransportInterface::class, function ($app) {
            $transport = $app['config']->get('laraspan.transport', 'queue');

            if ($transport === 'inline') {
                return new InlineTransport(
                    baseUrl: $app['config']->get('laraspan.url', ''),
                    token: $app['config']->get('laraspan.token', ''),
                );
            }

            return new QueueTransport(
                flushThreshold: $app['config']->get('laraspan.buffer.flush_threshold', 100),
            );
        });

        $this->app->singleton(Redactor::class, function ($app) {
            return new Redactor($app['config']->get('laraspan.redact', []));
        });

        $this->app->singleton(Sampler::class, function ($app) {
            return new Sampler($app['config']->get('laraspan.sampling', []));
        });

        $this->app->singleton(EventFilter::class, function () {
            return new EventFilter;
        });

        $this->app->singleton(CommandListener::class);
        $this->app->singleton(JobListener::class);
        $this->app->singleton(HttpClientListener::class);
        $this->app->singleton(MailListener::class);
        $this->app->singleton(NotificationListener::class);

        $this->app->singleton(SelfMonitoringGuard::class, function ($app) {
            return new SelfMonitoringGuard(
                laraSpanUrl: rtrim($app['config']->get('laraspan.url', ''), '/'),
                appUrl: rtrim($app['config']->get('app.url', ''), '/'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laraspan.php' => config_path('laraspan.php'),
        ], 'laraspan-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                TestCommand::class,
                FlushCommand::class,
                DeployCommand::class,
            ]);
        }

        if (! $this->app['config']->get('laraspan.enabled', true)) {
            return;
        }

        $this->registerMiddleware();
        $this->registerListeners();
        $this->registerTerminatingCallback();
        $this->registerScheduledFlush();
        $this->registerOctaneSupport();
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->pushMiddlewareToGroup('web', LaraSpanMiddleware::class);
        $router->pushMiddlewareToGroup('api', LaraSpanMiddleware::class);
    }

    protected function registerListeners(): void
    {
        $monitors = $this->app['config']->get('laraspan.monitors', []);

        if ($monitors['exceptions'] ?? true) {
            Event::listen(MessageLogged::class, ExceptionListener::class);
        }

        if ($monitors['queries'] ?? true) {
            Event::listen(QueryExecuted::class, QueryListener::class);
        }

        if ($monitors['requests'] ?? true) {
            Event::listen(RequestHandled::class, RequestListener::class);
        }

        if ($monitors['jobs'] ?? true) {
            Event::listen(JobProcessing::class, [JobListener::class, 'handleProcessing']);
            Event::listen(JobProcessed::class, [JobListener::class, 'handleProcessed']);
            Event::listen(JobFailed::class, [JobListener::class, 'handleFailed']);
        }

        if ($monitors['scheduler'] ?? true) {
            Event::listen(ScheduledTaskFinished::class, SchedulerListener::class);
        }

        if ($monitors['cache'] ?? true) {
            Event::listen(CacheHit::class, [CacheListener::class, 'handleHit']);
            Event::listen(CacheMissed::class, [CacheListener::class, 'handleMissed']);
            Event::listen(KeyWritten::class, [CacheListener::class, 'handleWritten']);
            Event::listen(KeyForgotten::class, [CacheListener::class, 'handleForgotten']);
        }

        if ($monitors['mail'] ?? true) {
            Event::listen(MessageSending::class, [MailListener::class, 'handleSending']);
            Event::listen(MessageSent::class, [MailListener::class, 'handleSent']);
        }

        if ($monitors['notification'] ?? true) {
            Event::listen(NotificationSending::class, [NotificationListener::class, 'handleSending']);
            Event::listen(NotificationSent::class, [NotificationListener::class, 'handleSent']);
        }

        if ($monitors['http_client'] ?? true) {
            Event::listen(RequestSending::class, [HttpClientListener::class, 'handleSending']);
            Event::listen(ResponseReceived::class, [HttpClientListener::class, 'handleResponse']);
            Event::listen(ConnectionFailed::class, [HttpClientListener::class, 'handleFailed']);
        }

        if ($monitors['command'] ?? true) {
            Event::listen(CommandStarting::class, [CommandListener::class, 'handleStarting']);
            Event::listen(CommandFinished::class, [CommandListener::class, 'handleFinished']);
        }

        if ($monitors['log'] ?? true) {
            Event::listen(MessageLogged::class, LogListener::class);
        }
    }

    protected function registerTerminatingCallback(): void
    {
        $this->app->terminating(function () {
            /** @var SelfMonitoringGuard $guard */
            $guard = $this->app->make(SelfMonitoringGuard::class);

            if ($guard->isSelfRequest()) {
                return;
            }

            /** @var EventBuffer $buffer */
            $buffer = $this->app->make(EventBuffer::class);

            $events = $buffer->flush();

            if (empty($events)) {
                return;
            }

            /** @var Sampler $sampler */
            $sampler = $this->app->make(Sampler::class);

            // Apply per-route sample rate override if set by Sample middleware
            $routeRate = request()?->attributes?->get('laraspan_sample_rate');
            if ($routeRate !== null) {
                $sampler->setOverride((float) $routeRate);
            }

            /** @var Redactor $redactor */
            $redactor = $this->app->make(Redactor::class);

            /** @var EventFilter $filter */
            $filter = $this->app->make(EventFilter::class);

            $filtered = array_filter($events, function (array $event) use ($sampler, $filter) {
                if ($filter->shouldReject($event)) {
                    return false;
                }

                return $sampler->shouldSample($event['type'] ?? 'unknown');
            });

            if (empty($filtered)) {
                return;
            }

            $redacted = array_map(fn (array $event) => $redactor->redact($event), $filtered);

            /** @var TransportInterface $transport */
            $transport = $this->app->make(TransportInterface::class);
            $transport->send(array_values($redacted));
        });
    }

    protected function registerScheduledFlush(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new FlushEventsJob)->everyFiveSeconds()->withoutOverlapping();
        });
    }

    protected function registerOctaneSupport(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        Event::listen(RequestReceived::class, function () {
            LaraSpanMiddleware::resetLifecycle();

            /** @var EventBuffer $buffer */
            $buffer = $this->app->make(EventBuffer::class);
            $buffer->reset();

            /** @var SelfMonitoringGuard $guard */
            $guard = $this->app->make(SelfMonitoringGuard::class);

            if ($guard->isSelfRequest()) {
                $buffer->pause();
            }
        });
    }
}
