# Schedule Authority package state

branch: `feature/tmetric-v3-schedule-authority-20260927`
status: `READY_FOR_PKG_01_CODE_ONLY_IMPLEMENTATION`

ERP TSA.01 static provider-contract spike is complete.
ERP TSA.02 architecture/tech-lead review is complete.
ERP TSA.03 static performance/egress review is complete.
ERP current handoff is TSA.04.

Planned package handoffs:
1. PKG.01_V3_SCHEDULE_BALANCE.md — current package implementation boundary
2. PKG.02_ARCH_REVIEW.md
3. PKG.03_PERFORMANCE_REVIEW.md

Reviewed performance contract:
- one Schedule logical read = one provider request before transport-level retry;
- no package member discovery or Schedule fan-out;
- one Balance logical read = one provider request before transport-level retry;
- ERP owns all Balance fan-out/cadence;
- package must not add hidden retries beyond common transport behavior;
- ERP automatic Schedule window/cadence limits remain ERP policy, not generic package limits.

Execution policy through ERP TSA.19:
- feature-branch code/documentation/test-code only;
- tests may be written but not run;
- no builds/provider calls/GitHub Actions/CI/deployment/Stage/main/Production actions.

Real provider/runtime validation is ERP TSA.20 only.
