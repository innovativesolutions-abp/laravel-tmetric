# TMetric API contract

Checked: 25 July 2026. Schedule/Time Balance v3.2.1 contract reviewed again on 27 September 2026.

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
(`GET`, `HEAD`, and `OPTIONS`). For HTTP 429, an explicit `Retry-After` is retried only when the full provider-requested wait fits within `max_retry_delay_seconds`; if it exceeds that local retry budget, the transport surfaces `RateLimitedException` immediately instead of retrying before the provider window. Mutations are single-attempt operations because
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
| Individual schedules | `GET /accounts/{accountId}/schedule` |
| Time balance | `GET /accounts/{accountId}/balance` |

Implemented documented writes:

| Operation | Endpoint | Body | Success |
| --- | --- | --- | --- |
| Change time-entry project | `PUT /accounts/{accountId}/timeentries/{timeEntryId}` | Numeric project/task/tag IDs plus preserved task, tags, start/end and optional note from a fresh complete entry | Updated time-entry JSON (`200`) or no body (`204`, returned as `null`) |

This package only transports and parses that mutation. It does not decide the
correct project, match Jira identities, persist an outbox, retry ambiguous
outcomes, or implement ERP authorization and reconciliation rules.

The schema does not document a v3 `GET` on `/accounts/{accountId}/members` or `/accounts/{accountId}/projects`. It documents `PATCH` for members and `POST` for projects. The package does not infer unsupported reads. Workspace-user discovery uses the documented project-report filter and therefore represents users whose report data is visible to the current token, not an administrative members snapshot.

The time-entry list accepts `userId`, `startDate`, and `endDate`. The schema does not describe a cursor or `updated_since` filter.

### Schedule

The documented v3.2.1 Schedule request is:

`GET /accounts/{accountId}/schedule?StartDate=YYYY-MM-DD&EndDate=YYYY-MM-DD`

TMetric documents `StartDate` and `EndDate` as optional. This package deliberately requires both dates so a generic client cannot accidentally issue an unbounded workspace schedule read.

There is no documented Schedule `userId` query. One logical `schedules()` call performs one provider request before the common transport's bounded read retries; the package never performs member discovery or per-member Schedule fan-out.

The provider OpenAPI is internally contradictory:
- endpoint/200 text describes schedules for all workspace members;
- the response schema references one `IndividualSchedule`;
- `IndividualSchedule.user` references one `UserBasic`;
- its example shows a one-element `user` array.

Until an authorized runtime confirmation, the package accepts only the two explicit forms represented by that specification:
- top-level one schedule object or a list of schedule objects;
- nested one user object or exactly one user in a one-element list.

Unknown envelopes and other list cardinalities raise `SchemaDriftException`. Schedule member IDs are required to remain integer-compatible as documented; no undocumented positivity minimum is imposed on response IDs.

Documented schedule fields are limited to `user` and `days[]`, with day fields `date`, `isWorking`, and `hours`. The package:
- preserves the original provider date-time string/offset;
- preserves `hours` as int/float without rounding;
- preserves missing values as missing/null typed state plus the existing raw escape hatch;
- does not invent schedule timezone, working-hours intervals, effective episodes, holiday/additional-workday categories, override provenance, or Beyond Schedule.

Because PHP associative JSON decoding cannot distinguish an empty object `{}` from an empty array `[]`, empty object-compatible structures may be representation-ambiguous after transport decoding. Non-empty undocumented list/envelope shapes still fail closed. TSA.20 must confirm that real Schedule/Balance payloads do not depend on an ambiguous empty root/container distinction before provider authority activation.

### Time Balance

The documented request is:

`GET /accounts/{accountId}/balance`

with optional positive integer query `userId`. Omitting `userId` requests the current authenticated user's balance; supplying it requests that user.

One logical `timeBalance()` call performs one provider request before the common transport's bounded read retries. The package never discovers users or fans out Balance requests.

The documented summary exposes optional `month`, `today`, and `week` objects. Each may expose:
- `requiredSeconds`;
- `actualSeconds`;
- `actualSecondsRounded`.

The OpenAPI does not mark those summary/value properties required, so missing values remain missing and are never defaulted to zero. Non-empty undocumented root envelopes fail closed instead of being interpreted as an empty balance.

No direct Beyond Schedule endpoint or field is documented in v3.2.1.

Both new reads require the documented HTTP `200` success status. An undocumented successful status such as `204` is treated as contract/schema drift rather than silently becoming an empty result.

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
| User group with members and supervisors | `GET /api/accounts/{accountId}/usergroups/{userGroupId}` |

Implemented documented write:

| Operation | Endpoint | Safety boundary |
| --- | --- | --- |
| Add one project member | `PUT /api/accounts/{accountId}/projects/{projectId}` | Preserves the complete fresh project body, adds one numeric `members[]` item, never retries, and requires a consumer-owned serialization/readback policy |

The legacy time-entry request documents `StartTime`, `EndTime`, `useUtcTime`, `includeDeleted`, and `truncate`.

The Timeline schema contains nested details with `activitySeconds` and `totalSeconds`. It also describes process/window fields, which this package intentionally removes from its DTOs for privacy.

The legacy Project schema contains full project settings, direct `members[]`,
and assigned `groups[]`. A project group references a UserGroup, whose
`members[]` and `supervisors[]` are distinct collections. Consumers that need
effective project access must resolve assigned groups and must not infer that a
group supervisor is also a group member.
Because the endpoint is a full-resource PUT without a documented ETag, the
package does not retry it and does not claim concurrency safety. A consuming
application must lock per project, GET immediately before PUT, and verify the
complete member set after the write.

Negative HTTP exceptions expose only bounded decoded JSON with sensitive keys
redacted, plus body length and SHA-256. They never expose headers, credentials,
proxy configuration, or an unbounded raw response body.

## Unresolved until the consuming ERP's authorized TSA.20 runtime gate

- token plan and permissions for each endpoint;
- whether all legacy endpoints remain supported for long-term integrations;
- actual Timeline segment granularity (TMetric materials have described both 10 and 15 minutes);
- real behavior of `includeDeleted`;
- pagination, maximum period, and truncation behavior where the schema is silent;
- actual rate-limit status, headers, and `Retry-After` format;
- response differences between active timers, manual entries, and deleted entries.

Until these points are checked, legacy support is experimental and disabled by default.


### Schedule Authority runtime gates

The package implementation above is based on static v3.2.1 contract evidence and synthetic/fake test code only. No real TMetric request was made while adding it.

The consuming ERP must verify before activating provider-backed schedule authority:
- actual Schedule top-level and nested-user shape;
- authorized visibility of other workspace members;
- date/timezone semantics;
- real `hours` precision;
- missing-field behavior;
- Schedule response size/latency/rate-limit behavior;
- explicit-other-user Balance permission;
- any undocumented richer Schedule/Beyond-Schedule surface.

If runtime evidence contradicts the static compatibility contract, the consuming application must keep provider authority disabled and return the package/application code to review rather than guessing a third response shape.
