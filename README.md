# Laravel TMetric

A typed Laravel client for documented TMetric reads and narrowly scoped writes. The package deliberately contains no Jira, calendar, AI, database, synchronization, reconciliation, idempotency-outbox, or ERP business logic.

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

## Usage

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

The documented v3 time-entry project update is also available. Pass the fresh,
complete time-entry DTO so the package can preserve the task, tags, times, and
optional note required by workspaces that enforce task links:

```php
$updated = TMetric::connection()->v3()->updateTimeEntryProject(
    entry: $entry,
    projectId: 9002,
);
```

This sends `PUT /accounts/{accountId}/timeentries/{timeEntryId}` with numeric
project/task/tag IDs and the complete preserved update body. TMetric may return an updated time entry or an
empty `204`, represented by `null`. Mutations are never retried automatically:
a connection loss, timeout, `408`, `429`, or `5xx` can have an unknown outcome.
The transport refuses automatic retries for mutating HTTP methods even if a
custom request tries to enable its read-retry flag. Consumers must persist
their own idempotent outbox, reconcile against a later
authoritative synchronization, and decide whether a retry is safe. Read
requests retain bounded transient retries.

When legacy v2 is explicitly enabled, a consumer may fetch the documented full
project model and idempotently add one numeric member while preserving the
complete project body:

```php
$project = TMetric::connection()->legacy()->project(9002);
$updatedProject = TMetric::connection()->legacy()->addProjectMember($project, 101);
```

The legacy project mutation is also a single non-retrying request. Consumers
must serialize concurrent project edits and verify membership with a fresh GET.

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

Available v3 writes:

- change the project assigned to an existing time entry.

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

The client maps authentication, authorization, not-found, rate-limit, transient transport, malformed JSON, and schema-drift failures to typed exceptions. For safe read methods, timeouts, connection failures, HTTP 408/429, and server errors are retried with bounded attempts honoring `Retry-After`. Mutating methods fail closed after the first ambiguous result and are not automatically retried.

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
