# PKG.01 — v3 IndividualSchedule / TimeBalance reads

Precondition: ERP TSA.01 provider contract result exists and is reviewed.

Implement only proved documented/observed read behavior.

Expected when proved:
- `Data\IndividualSchedule` and nested typed value objects;
- `Data\TimeBalance`;
- V3Client schedule read;
- V3Client balance read;
- exact member selector/query only if TSA.01 proved it;
- schema drift checks;
- fake tests/request assertions;
- docs/API_CONTRACT.md update;
- README usage.

Preserve unknown optional fields through existing raw escape hatch where compatible.
No real network in package tests.
No ERP logic.
Stop after implementation and package tests written/executed according to current package workflow.
