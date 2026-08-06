# TMetric API contract

Checked: 25 July 2026.

Sources:

- `https://app.tmetric.com/api-docs/`
- `https://app.tmetric.com/api-docs/v2/`
- `https://tmetric.com/help/rest-api-reference`

No authenticated TMetric request was made during this verification.

The initial package baseline is PHP 8.2+ and Laravel `^12.62.0`. The development suite is pinned to Laravel `12.62.0`, matching the version currently locked and running in ABA ERP. Laravel 11 is intentionally outside the support matrix.

## Transport policy

Connections may specify an explicit `socks5h://host:port` proxy or a protected,
credential-free HTTP CONNECT proxy as `http://host:port`. When SOCKS is used,
PHP cURL support for remote-hostname SOCKS5 is required. Invalid proxy
configuration is rejected before transport. `socks5://`, HTTPS proxy URLs,
userinfo, paths, queries, and fragments are rejected.

The Laravel HTTP transport supplies the same scalar Guzzle `proxy` option on
every bounded attempt, keeps redirects disabled, and never retries without the
proxy. The `h` delegates destination hostname resolution through the proxy
path. TLS verification remains end-to-end against the requested TMetric
hostname.

For an HTTP proxy, Guzzle uses CONNECT for HTTPS requests. The package still
validates the TMetric server certificate and hostname end-to-end and never
retries without the selected proxy.

The package preserves the configured transport on every attempt and never
falls back from a configured proxy to a direct retry. The consuming application
remains responsible for requiring the proxy when its policy demands it, plus
proxy reachability, infrastructure allowlists, workload isolation, and any
network-level direct-egress controls. Proxy-bearing configuration is redacted
from debug output and cannot be serialized.

Automatic transient retries are enabled by default only for safe read methods
(`GET`, `HEAD`, and `OPTIONS`). Mutations are single-attempt operations because
a connection loss, timeout, `408`, `429`, or `5xx` can occur after TMetric has
already applied the change. The consuming application must own durable
idempotency, unknown-outcome reconciliation, and any later retry decision.
The generic request object's read-retry flag cannot enable retries for a
mutating HTTP method.

## v3

Official OpenAPI version: `3.2.1`. Base path: `/api/v3`.

Implemented documented reads:

| Operation | Endpoint |
| --- | --- |
| Current user | `GET /user` |
| Clients | `GET /accounts/{accountId}/clients` |
| Tasks | `GET /accounts/{accountId}/tasks` |
| Time-entry projects | `GET /accounts/{accountId}/timeentries/projects` |
| Time entries | `GET /accounts/{accountId}/timeentries` |
| Latest time entry | `GET /accounts/{accountId}/timeentries/latest` |
| Tracking statuses | `GET /accounts/{accountId}/timeentries/statuses` |
| Report-visible workspace users | `GET /accounts/{accountId}/reports/projects/filter` |

Implemented documented writes:

| Operation | Endpoint | Body | Success |
| --- | --- | --- | --- |
| Change time-entry project | `PUT /accounts/{accountId}/timeentries/{timeEntryId}` | Numeric project/task/tag IDs plus preserved task, tags, start/end and optional note from a fresh complete entry | Updated time-entry JSON (`200`) or no body (`204`, returned as `null`) |

This package only transports and parses that mutation. It does not decide the
correct project, match Jira identities, persist an outbox, retry ambiguous
outcomes, or implement ERP authorization and reconciliation rules.

The schema does not document a v3 `GET` on `/accounts/{accountId}/members` or `/accounts/{accountId}/projects`. It documents `PATCH` for members and `POST` for projects. The package does not infer unsupported reads. Workspace-user discovery uses the documented project-report filter and therefore represents users whose report data is visible to the current token, not an administrative members snapshot.

The time-entry list accepts `userId`, `startDate`, and `endDate`. The schema does not describe a cursor or `updated_since` filter.

The tasks endpoint documents HTTP 206 as “Only first 500 tasks returned” without a pagination mechanism. The package raises a typed `PartialContentException` for 206 so consumers cannot mistake a truncated result for a complete snapshot.

## Legacy v2

The official document identifies itself as `v2` and uses `/api/...` paths.

Implemented documented reads:

| Operation | Endpoint |
| --- | --- |
| Detailed report | `GET /api/reports/detailed` |
| Numeric Timeline | `GET /api/timeline/{accountId}` |
| User time entries | `GET /api/accounts/{accountId}/timeentries/{userProfileId}` |
| Full project | `GET /api/accounts/{accountId}/projects/{projectId}` |

Implemented documented write:

| Operation | Endpoint | Safety boundary |
| --- | --- | --- |
| Add one project member | `PUT /api/accounts/{accountId}/projects/{projectId}` | Preserves the complete fresh project body, adds one numeric `members[]` item, never retries, and requires a consumer-owned serialization/readback policy |

The legacy time-entry request documents `StartTime`, `EndTime`, `useUtcTime`, `includeDeleted`, and `truncate`.

The Timeline schema contains nested details with `activitySeconds` and `totalSeconds`. It also describes process/window fields, which this package intentionally removes from its DTOs for privacy.

The legacy Project schema contains full project settings and `members[]`.
Because the endpoint is a full-resource PUT without a documented ETag, the
package does not retry it and does not claim concurrency safety. A consuming
application must lock per project, GET immediately before PUT, and verify the
complete member set after the write.

Negative HTTP exceptions expose only bounded decoded JSON with sensitive keys
redacted, plus body length and SHA-256. They never expose headers, credentials,
proxy configuration, or an unbounded raw response body.

## Unresolved until an authorized real-workspace spike

- token plan and permissions for each endpoint;
- whether all legacy endpoints remain supported for long-term integrations;
- actual Timeline segment granularity (TMetric materials have described both 10 and 15 minutes);
- real behavior of `includeDeleted`;
- pagination, maximum period, and truncation behavior where the schema is silent;
- actual rate-limit status, headers, and `Retry-After` format;
- response differences between active timers, manual entries, and deleted entries.

Until these points are checked, legacy support is experimental and disabled by default.
