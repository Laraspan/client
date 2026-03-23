# Changelog

All notable changes to the LaraSpan Client will be documented in this file.

This project follows [Keep a Changelog](https://keepachangelog.com/) and [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- 11 event monitors: exceptions, requests, queries, jobs, commands, scheduler, cache, mail, notifications, HTTP client, and logs
- Queue-based transport with Redis buffering for sub-millisecond overhead on request terminate
- Inline transport for development environments without Redis
- `ExecutionState` architecture for centralized request lifecycle tracking
- 7 lifecycle stages (`Bootstrap`, `BeforeMiddleware`, `Action`, `Render`, `AfterMiddleware`, `Sending`, `Terminating`) with per-stage duration tracking, inspired by Laravel Nightwatch's execution timeline
- `ExecutionStage` enum with `forHttp()` and `forCommand()` stage sets
- Per-type sampling rates configurable via config or env vars (`LARASPAN_SAMPLE_*`)
- `Sample` middleware for per-route sampling (`Sample::rate()`, `Sample::always()`, `Sample::never()`)
- Programmatic sampling override via `LaraSpan::sample()` and `LaraSpan::dontSample()`
- Exception bypass: exceptions are always captured regardless of sampling rate
- `UserProvider` with automatic auth resolution and custom resolver via `LaraSpan::user()`
- User context remembered across logout within the same request
- Context propagation from requests to jobs: `trace_id` and `user_id` injected into job payloads and restored on execution
- `EventFilter` API for rejecting events before buffering (`rejectQueries`, `rejectJobs`, `rejectCacheKeys`, `rejectMail`, `rejectNotifications`, `rejectLogs`, `rejectHttpClient`, `rejectCommands`)
- `HeaderRedactor` with scheme-aware redaction: preserves auth scheme for Authorization headers, preserves cookie names for Cookie headers
- Recursive payload redaction with case-insensitive key matching
- `LazyValue` for deferred JSON serialization of event data
- `LaraSpan::pause()`, `resume()`, and `ignore()` for programmatic capture control
- N+1 query detection with configurable threshold
- Slow request, query, job, and HTTP client flagging with configurable thresholds
- Buffer safety cap (`max_events_per_request`) to prevent memory exhaustion
- Redis queue size limit (`max_queue_size`) with oldest-event trimming
- Vendor event filtering to exclude framework internals
- `ignore_paths` with wildcard pattern support for skipping health checks and internal endpoints
- `ignore_exceptions` for suppressing common exception types (404, auth, validation)
- Multi-tenant context support via `EventBuffer::setContext()`
- Self-monitoring protection: auto-pauses on `/api/ingest` and `/api/deploy` requests
- Laravel Octane support with automatic state reset between requests
- `laraspan:install` command to publish config and seed `.env`
- `laraspan:test` command to verify server connectivity
- `laraspan:flush` command to manually drain the Redis buffer
- `laraspan:deploy` command for deployment tracking with version, commit, and deployer
- Configurable Redis connection name for queue transport
- Configurable HTTP timeout for server communication
- Per-monitor toggle via config and env vars (`LARASPAN_MONITOR_*`)
- Configurable header redaction list via `LARASPAN_REDACT_HEADERS` env var
- Source code context capture around exception stack frames (opt-in)
- SQL binding capture (opt-in)
- Request header and payload capture (opt-in)
- Gzip compression for batch event payloads
- Support for PHP 8.2+ and Laravel 11, 12, 13

### Changed
- Nothing yet (initial release)

### Fixed
- Nothing yet (initial release)
