# Schedule Authority package state

branch: `feature/tmetric-v3-schedule-authority-20260927`
status: `READY_FOR_PKG_03_STATIC_PERFORMANCE_REVIEW`

ERP TSA.04 package implementation is complete in code-only mode.
ERP TSA.05 package architecture/tech-lead review is complete.

PKG.02 static review findings fixed in the package branch:
- unknown non-empty Time Balance root envelopes now fail closed;
- Schedule and Balance accept only documented HTTP `200` success;
- Schedule response member ID is required to be integer-compatible, without inventing an undocumented positivity minimum;
- the existing object/list compatibility remains limited to the two contradictions explicitly present in TMetric v3.2.1;
- missing/null/zero semantics remain separated;
- provider date-time offset and numeric hours remain unmodified;
- no existing public package method/DTO behavior was changed; this work is additive.

Exact implementation-review diff remains limited to:
- `README.md`
- `docs/API_CONTRACT.md`
- `docs/ScheduleAuthority/STATE.md`
- `src/Data/IndividualSchedule.php`
- `src/Data/IndividualScheduleDay.php`
- `src/Data/TimeBalance.php`
- `src/Data/TimeBalanceSummary.php`
- `src/V3Client.php`
- `tests/Feature/V3ScheduleBalanceTest.php`

Written test code now covers the architecture-review fixes, including undocumented successful statuses, unknown Balance envelopes, and invalid schedule member identity representations.

Important:
- tests were **written but not executed**;
- no build/lint/provider request/GitHub Action/CI/deployment was executed;
- no Stage/main/Production action occurred.

Planned package handoffs:
1. PKG.01_V3_SCHEDULE_BALANCE.md — complete, tests not run
2. PKG.02_ARCH_REVIEW.md — complete
3. PKG.03_PERFORMANCE_REVIEW.md — next

PKG.03 must focus on:
- one logical read = one provider request before common transport retry;
- no hidden discovery/fan-out;
- root object/list normalization memory behavior;
- raw + typed DTO materialization cost;
- no avoidable duplicate payload copies;
- Retry-After/common retry behavior;
- empty-object/empty-list associative decode ambiguity as a TSA.20 runtime-contract check, not a guessed package rule.

Execution policy through ERP TSA.19:
- feature-branch code/documentation/test-code only;
- tests may be written but not run;
- no builds/provider calls/GitHub Actions/CI/deployment/Stage/main/Production actions.

Real provider/runtime validation is ERP TSA.20 only.
