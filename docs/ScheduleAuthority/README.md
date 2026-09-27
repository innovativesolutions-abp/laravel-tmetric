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

The real-workspace contract is owned by ERP TSA.01. Do not implement undocumented member selectors until TSA.01 proves them.

Standard package tests are fake/synthetic and must not make real network calls.
