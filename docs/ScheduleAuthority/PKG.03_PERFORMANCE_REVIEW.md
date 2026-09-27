# PKG.03 — static performance/retry review

Review source/test-code only:
- one schedule logical operation = one request;
- no duplicate current-user/workspace lookup;
- response materialization for workspace × bounded date-window cardinality;
- strict normalization does not duplicate full raw payloads unnecessarily;
- retry amplification;
- 429/Retry-After;
- no initialization/config/discovery network call;
- no unbounded logs/raw payload copies;
- Balance per-user fan-out is explicit to ERP rather than hidden in package behavior.

Publish exact request-count/materialization assumptions for ERP TSA.13.

Do not run tests, benchmarks, builds, provider calls, GitHub Actions/CI or deployment. Runtime measurement is ERP TSA.20.
