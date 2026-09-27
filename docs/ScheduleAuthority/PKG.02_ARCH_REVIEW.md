# PKG.02 — architecture/tech-lead review

Review PKG.01 exact diff statically.

Check:
- official contract fidelity;
- only the two explicit OpenAPI contradictory forms are accepted;
- unknown envelopes fail closed;
- member identity handling;
- optional/null/missing compatibility;
- missing values are not defaulted;
- fractional hours are preserved and never rounded;
- date-time offset is preserved and never timezone-shifted;
- no guessed fields/member selector;
- no ERP business logic;
- no credential/raw payload leakage;
- transport/retry semantics remain read-safe;
- backward compatibility.

Fix in-scope defects, update PKG.03 and ERP TSA handoffs when necessary.

Do not run tests/builds/provider calls/GitHub Actions/CI or deployment.
