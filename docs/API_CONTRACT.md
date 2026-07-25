# TMetric API contract

Checked: 25 July 2026.

Sources:

- `https://app.tmetric.com/api-docs/`
- `https://app.tmetric.com/api-docs/v2/`
- `https://tmetric.com/help/rest-api-reference`

No authenticated TMetric request was made during this verification.

The initial package baseline is PHP 8.2+ and Laravel `^12.62.0`. The development suite is pinned to Laravel `12.62.0`, matching the version currently locked and running in ABA ERP. Laravel 11 is intentionally outside the support matrix.

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

The schema does not document a v3 `GET` on `/accounts/{accountId}/members` or `/accounts/{accountId}/projects`. It documents `PATCH` for members and `POST` for projects. The package does not infer unsupported reads.

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

The legacy time-entry request documents `StartTime`, `EndTime`, `useUtcTime`, `includeDeleted`, and `truncate`.

The Timeline schema contains nested details with `activitySeconds` and `totalSeconds`. It also describes process/window fields, which this package intentionally removes from its DTOs for privacy.

## Unresolved until an authorized real-workspace spike

- token plan and permissions for each endpoint;
- whether all legacy endpoints remain supported for long-term integrations;
- actual Timeline segment granularity (TMetric materials have described both 10 and 15 minutes);
- real behavior of `includeDeleted`;
- pagination, maximum period, and truncation behavior where the schema is silent;
- actual rate-limit status, headers, and `Retry-After` format;
- response differences between active timers, manual entries, and deleted entries.

Until these points are checked, legacy support is experimental and disabled by default.
