# PKG.03 — static performance/retry review

Review source/test-code only.

Request-count invariants:
- one Schedule logical operation = exactly one provider request before common transport-level retry;
- no duplicate current-user/workspace lookup;
- no per-member Schedule fan-out;
- one Balance logical operation = exactly one provider request before common transport-level retry;
- package never discovers members or fans out Balance internally.

Review:
- response materialization for workspace × bounded date-window cardinality;
- root object/list compatibility normalization without avoidable second full-root copies;
- nested raw/typed representation memory cost;
- retry amplification;
- 429/Retry-After;
- no method-level retry on top of transport retry;
- no initialization/config/discovery network call;
- no unbounded logs/raw payload copies;
- no hidden queue/business orchestration in package.

ERP TSA.03 owns automatic cadence/window/fan-out:
- Schedule due every 6 hours after activation;
- one Schedule request per due authority workspace;
- normal ERP window 41 inclusive dates;
- bootstrap/manual ERP maximum 64 dates/request;
- Balance once/24h for explicit authority-linked members only;
- Balance fan-out is serialized/bounded by ERP.

Publish exact package request-count/materialization assumptions for ERP TSA.13/TSA.15.

Do not run tests, benchmarks, builds, provider calls, GitHub Actions/CI or deployment. Runtime measurement is ERP TSA.20.
