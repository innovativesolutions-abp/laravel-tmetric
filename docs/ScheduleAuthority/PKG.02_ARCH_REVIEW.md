# PKG.02 — architecture/tech-lead review

Review PKG.01 exact diff.

Check:
- official/observed contract fidelity;
- member identity handling;
- optional/null field compatibility;
- no guessed fields;
- no ERP business logic;
- no credential/raw payload leakage;
- transport/retry semantics remain read-safe;
- backward compatibility.

Fix in-scope defects, update PKG.03 and ERP TSA handoffs when necessary.
