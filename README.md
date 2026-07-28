# Laravel TMetric

A typed, read-only Laravel client for TMetric. The package deliberately contains no Jira, calendar, AI, database, synchronization, or ERP business logic.

> [!IMPORTANT]
> The current `main` branch is an unreleased development version. There is no tag or Packagist registration yet. The legacy v2 surface is disabled by default and has been validated against the official schema and synthetic fixtures, not a real TMetric workspace.

## Requirements

- PHP 8.2 or newer
- PHP cURL extension with SOCKS5 hostname support
- Laravel `^12.62.0`

The development suite is pinned to Laravel `12.62.0`, the version currently locked and running in ABA ERP. Consumers may use later compatible Laravel 12 patch releases.

## Installation before the first tagged release

Add the VCS repository to the consuming application's `composer.json`, then require the development branch:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/innovativesolutions-abp/laravel-tmetric"
        }
    ]
}
```

```bash
composer require innovativesolutions-abp/laravel-tmetric:dev-main
php artisan vendor:publish --tag=tmetric-config
```

Set `TMETRIC_TOKEN` and `TMETRIC_ACCOUNT_ID` in the consuming application's
protected environment. To route this client through SOCKS, also set
`TMETRIC_PROXY_URL=socks5h://host:port` or a protected HTTP CONNECT proxy such
as `TMETRIC_PROXY_URL=http://host:port`. Local-DNS `socks5://`, HTTPS proxy
URLs, credentials, paths, queries, and fragments are rejected. Do not commit
any of these values.

Proxy support is optional at this generic package layer. A consuming
application that requires controlled egress must enforce the presence of the
proxy before constructing a connection. When configured, this package never
retries that TMetric client request without the selected proxy. It does not
prevent a consuming application or unrelated HTTP client from using the host's
ordinary network route; mandatory policy and network-level egress controls
remain the consumer's responsibility.

## Read-only usage

```php
use DateTimeImmutable;
use InnovativeSolutions\TMetric\Facades\TMetric;

$profile = TMetric::connection()->v3()->user();

$entries = TMetric::connection()->v3()->timeEntries(
    userId: '101',
    startDate: new DateTimeImmutable('2026-07-01'),
    endDate: new DateTimeImmutable('2026-07-31'),
);

$statuses = TMetric::connection()->v3()->timeTrackingStatuses();
$reportUsers = TMetric::connection()->v3()->reportUsers();
```

For database-backed connection settings, a consuming ERP may build a runtime connection after decrypting the token inside the request/job:

```php
$connection = TMetric::connect([
    'token' => $decryptedToken,
    'account_id' => $accountId,
    'legacy_enabled' => false,
    'proxy' => 'socks5h://tmetric-egress:1080',
]);
```

Pass only the ERP connection record ID through queue payloads. Resolve and decrypt the token immediately before creating the runtime connection; `ConnectionConfig` deliberately refuses serialization.

The example proxy contains no credentials because it is expected to be isolated
inside a private application network. `socks5h` delegates hostname resolution
to the proxy path while the package explicitly requires HTTPS certificate and
hostname verification end-to-end in PHP. Redirect following remains disabled,
and every bounded retry uses the same proxy.

For an HTTP proxy:

```php
$connection = TMetric::connect([
    'token' => $decryptedToken,
    'account_id' => $accountId,
    'proxy' => 'http://private-proxy:8890',
]);
```

The package passes this endpoint to Guzzle as an HTTP proxy. HTTPS TMetric
requests use CONNECT, so TLS certificate and hostname verification remain
end-to-end between PHP and TMetric. Use a credential-free endpoint only on a
private network with source and destination restrictions.

Available v3 reads:

- current user;
- clients;
- tasks;
- projects available for time entry;
- time entries for a user and date range;
- latest time entry;
- current time-tracking statuses.
- workspace users visible to the current user in project reports.

The official v3 `3.2.1` schema does not document `GET /members` or a general `GET /projects`. The package uses the documented project-report filter for the workspace users visible to the current token, but it does not treat that list as a writable members directory.

## Legacy v2

Legacy endpoints are isolated and disabled by default:

```php
'legacy_enabled' => true,
```

```php
$report = TMetric::connection()->legacy()->detailedReport($from, $to, ['101']);
$timeline = TMetric::connection()->legacy()->timeline('101', $from, $to);
$entries = TMetric::connection()->legacy()->timeEntries('101', $from, $to, includeDeleted: true);
```

Timeline DTOs expose numeric activity only. Process names and window titles are removed from both typed fields and the DTO raw escape hatch.

## Testing without network access

```php
use InnovativeSolutions\TMetric\Facades\TMetric;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Testing\Fixture;

TMetric::fake([
    Fixture::load('v3-working-day'),
]);

$entries = TMetric::connection()->v3()->timeEntries('101', $from, $to);

TMetric::assertRequested(
    fn (Request $request) => $request->operation === 'time_entries.list',
);
```

An unplanned fake request fails closed. Package tests also call Laravel's
`Http::preventStrayRequests()`. Tests for a consuming application's mandatory
egress policy should provide a synthetic valid proxy URI and separately verify
that the application rejects an absent proxy before calling this package.

## Error model

The client maps authentication, authorization, not-found, rate-limit, transient transport, malformed JSON, and schema-drift failures to typed exceptions. Only timeouts, connection failures, HTTP 408/429, and server errors are retried. Retries are bounded and honor `Retry-After`.

HTTP 206 is rejected with `PartialContentException` rather than returning a silently truncated collection. The official tasks endpoint may return only its first 500 tasks and does not document a pagination mechanism.

Exception messages and diagnostic contexts never contain the Bearer token,
Authorization header, proxy URI, or raw response body. Proxy, connection, and
manager configuration cannot be serialized.

## API evidence

The implemented contract was checked on 25 July 2026 against:

- official TMetric v3 OpenAPI `3.2.1`;
- official TMetric legacy v2 OpenAPI marked `v2`.

See [API contract notes](docs/API_CONTRACT.md) for endpoints, verified facts, and unresolved real-workspace questions.

## Security

Please read [SECURITY.md](SECURITY.md). Never submit real credentials or personal TMetric payloads in an issue, fixture, log, or pull request.

## License

MIT
