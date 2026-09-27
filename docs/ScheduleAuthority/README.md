# TMetric v3 Schedule Authority package work

Branch: `feature/tmetric-v3-schedule-authority-20260927`
Base at creation: `codex/29050-tmetric-team-membership-provisioning`

This branch adds only generic typed TMetric v3 reads needed by the ERP schedule-authority workstream.

It must not contain:
- ERP persistence;
- Identity models;
- schedule authority business rules;
- Team Hours calculations;
- queue/sync orchestration.

The statically reviewed provider contract is owned by ERP TSA.01/TSA.02.
Do not invent undocumented member selectors, fields, envelopes, timezone semantics or precision.

The official v3.2.1 Schedule contract contains two explicit contradictions that package code may support conservatively:
- root one schedule object vs textual list/all-workspace-members semantics;
- `user: UserBasic` schema vs one-element `user` array example.

Support only those explicit forms and fail closed on any third shape. Real provider shape is a TSA.20 runtime gate.

Standard package tests are fake/synthetic and must not make real network calls.

## Execution policy

Until ERP TSA.20:
- code/documentation/test-code only;
- do not run package tests/builds/linters;
- do not make provider requests;
- do not run GitHub Actions/CI;
- do not deploy or touch Stage/main/Production.
