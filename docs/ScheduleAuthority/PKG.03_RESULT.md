# PKG.03 Result — static performance/retry review

Date: 2026-09-27

Status: **PASS AFTER RETRY FIX**

No tests, benchmarks, builds, provider requests, CI/GitHub Actions, deployment, Stage/main/Production actions, or runtime measurements were executed.

## Request-count result

Schedule:
- one `V3Client::schedules()` call creates one `Request` and one transport `send()`;
- no current-user/workspace/member discovery;
- no package fan-out.

Balance:
- one `V3Client::timeBalance()` call creates one `Request` and one transport `send()`;
- no user discovery;
- no package fan-out.

Physical HTTP attempts can exceed one only through the common safe-read retry loop.

## Retry finding fixed

The existing transport previously truncated a long provider `Retry-After` to `max_retry_delay_seconds` and retried early.

Example:
- provider: `Retry-After: 120`;
- configured max retry delay: 30 seconds;
- previous behavior: sleep 30 seconds and retry before the provider window.

This could amplify 429 throttling.

Fix:
- if HTTP 429 carries an explicit Retry-After longer than the configured local retry-delay budget, transport immediately surfaces `RateLimitedException`;
- it does not sleep and does not make an early retry;
- shorter Retry-After values keep the existing bounded retry behavior.

Synthetic test code was added to require:
- Retry-After 120;
- one physical HTTP attempt;
- zero sleeper calls;
- exception retains retryAfterSeconds=120 and attempts=1.

The test was not run.

## Materialization result

For a Schedule list with M members and D days/member:
- root compatibility validation: O(M);
- DTO mapping: O(M × D);
- total remains O(M × D).

The current list-root path performs one extra O(M) validation pass. Removing it would require moving object/list discrimination into another layer and gives no meaningful static win relative to day parsing.

Memory:
- response JSON is decoded once by the common transport;
- Schedule rows are not deep-cloned explicitly;
- `DataObject` stores raw arrays;
- nested typed DTOs also store their nested raw arrays;
- PHP arrays use copy-on-write, and the parser does not mutate provider arrays;
- source inspection therefore shows object/zval/reference overhead, not an intentional second deep copy of the full payload.

This is not runtime memory proof. TSA.20 must measure peak RSS for the actual workspace × window payload.

## Logging/privacy result

The new Schedule/Balance code:
- does not log raw provider payloads;
- does not serialize DTOs;
- does not add diagnostics containing provider rows;
- inherits existing bounded sanitized negative-response details.

## Empty JSON ambiguity

Associative PHP decode cannot distinguish empty JSON object from empty JSON array once both become `[]`.

Non-empty undocumented shapes fail closed. Empty-container behavior remains a mandatory TSA.20 provider-contract check rather than a speculative package transport redesign.

## ERP implication

Healthy-state logical request envelope remains:
- Schedule: 4 logical reads/day/workspace at 6-hour freshness;
- Balance: A logical reads/day for A authority-linked users.

However common transport may use up to configured max attempts on retryable failures.

ERP TSA.13/TSA.15 must therefore implement failure backoff / next-attempt gating so an hourly dispatcher cannot re-run a still-due provider read every hour after exhausted transient retries.

Recommended static policy:
- Schedule transient/429 after transport exhaustion: automatic backoff 60m, then 180m, then 360m cap;
- Balance provider-wide transient/429 after transport exhaustion: stop chain and next automatic attempt no earlier than 360m;
- authentication/authorization/schema/unsupported-contract failures: mark action-required/blocked and suppress automatic provider retries until configuration/provider-contract action;
- local lock contention may retry quickly because no provider call occurred.

## Decision

PASS. Package static work is complete for this workstream until later static reviews require a correction.

Proceed with ERP TSA.07 after ERP TSA.06 Result/STATE is recorded.
