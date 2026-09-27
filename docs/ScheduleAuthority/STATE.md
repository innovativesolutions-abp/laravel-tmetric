# Schedule Authority package state

branch: `feature/tmetric-v3-schedule-authority-20260927`
status: `WAITING_FOR_ERP_TSA_03_STATIC_PERFORMANCE_REVIEW`

ERP TSA.01 static provider-contract spike is complete.
ERP TSA.02 architecture/tech-lead review is complete and defines the strict compatibility/fail-closed rules.
Do not execute PKG.01 until ERP TSA.03 advances to TSA.04.

Planned package handoffs:
1. PKG.01_V3_SCHEDULE_BALANCE.md
2. PKG.02_ARCH_REVIEW.md
3. PKG.03_PERFORMANCE_REVIEW.md

Execution policy through ERP TSA.19:
- feature-branch code/documentation/test-code only;
- tests may be written but not run;
- no builds/provider calls/GitHub Actions/CI/deployment/Stage/main/Production actions.

Real provider/runtime validation is ERP TSA.20 only.
