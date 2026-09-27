# Schedule Authority package state

branch: `feature/tmetric-v3-schedule-authority-20260927`
status: `READY_FOR_PKG_02_STATIC_ARCH_REVIEW`

ERP current workstream handoff: TSA.04 is complete in code-only mode.
Package PKG.01 source/test-code/docs are complete.

Implemented source:
- `src/Data/IndividualSchedule.php`
- `src/Data/IndividualScheduleDay.php`
- `src/Data/TimeBalance.php`
- `src/Data/TimeBalanceSummary.php`
- `src/V3Client.php` schedule/balance reads

Written test code:
- `tests/Feature/V3ScheduleBalanceTest.php`

Documented:
- `docs/API_CONTRACT.md`
- `README.md`

PKG.01 guarantees by static inspection:
- bounded Schedule call requires start/end dates;
- exact provider query keys `StartDate`/`EndDate`;
- no schedule `userId`;
- one logical Schedule call = one provider request before common transport retries;
- root single-object/list and nested user-object/one-element-list are the only compatibility forms accepted;
- unknown envelopes/cardinalities fail as schema drift;
- provider date-time/offset and fractional hours are preserved;
- missing values are not defaulted;
- Balance optional `userId` is validated as a positive integer-compatible ID;
- one logical Balance call = one provider request before common transport retries;
- package performs no Schedule/Balance member discovery or fan-out.

Important:
- tests were **written but not executed**;
- no build/lint/provider request/GitHub Action/CI/deployment was executed;
- no Stage/main/Production action occurred.

Planned package handoffs:
1. PKG.01_V3_SCHEDULE_BALANCE.md — complete, not runtime-verified
2. PKG.02_ARCH_REVIEW.md — next package review
3. PKG.03_PERFORMANCE_REVIEW.md

Execution policy through ERP TSA.19:
- feature-branch code/documentation/test-code only;
- tests may be written but not run;
- no builds/provider calls/GitHub Actions/CI/deployment/Stage/main/Production actions.

Real provider/runtime validation is ERP TSA.20 only.
