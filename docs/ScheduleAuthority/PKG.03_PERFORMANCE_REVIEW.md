# PKG.03 — performance/retry review

Review:
- request count per logical operation;
- no duplicate current-user/workspace lookup;
- response materialization;
- retry amplification;
- 429/Retry-After;
- no initialization/config/discovery network call;
- no unbounded logs/raw payload copies.

Publish exact request-count assumptions for ERP TSA.13.
