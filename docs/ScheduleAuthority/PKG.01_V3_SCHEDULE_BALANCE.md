# PKG.01 — v3 IndividualSchedule / TimeBalance reads

Precondition: ERP TSA.03 static performance/egress review is complete and ERP current handoff is TSA.04.

Implement only the statically proved/reviewed contract.

## Schedule request

Implement one bounded read:
- `GET /accounts/{accountId}/schedule`;
- exact optional-provider keys used as required package inputs: `StartDate`, `EndDate`;
- package method requires both dates and validates start <= end;
- no schedule `userId`;
- no discovery/current-user pre-request.

## Schedule response

Official v3.2.1 contradicts itself. Accept only the explicit documented forms:

Root:
- one IndividualSchedule object; or
- list of IndividualSchedule objects.

Nested `user`:
- one UserBasic object; or
- exactly one UserBasic in a one-element list.

Normalize to a typed collection.

Reject:
- unknown envelope;
- zero/multi-element nested user list;
- malformed documented field types.

DTO/value surface:
- member `user` / required `user.id` when present;
- `days[]`;
- `date` as original provider date-time/offset;
- `isWorking`;
- numeric `hours` preserved without rounding.

The schedule/day schema does not declare every property required. Preserve missing distinctions; do not create fake defaults.

Do not add working-hours intervals, schedule timezone, effective episodes, holiday/additional-day categories, override provenance or Beyond Schedule.

## Time Balance

Implement:
- `GET /accounts/{accountId}/balance`;
- optional positive integer query `userId`.

Typed data:
- optional `month`, `today`, `week`;
- optional nested `requiredSeconds`, `actualSeconds`, `actualSecondsRounded`.

Missing is not zero.

## Fake/synthetic tests to write

- exact request path/query/case;
- bounded date validation;
- one-object and list-root schedule shapes;
- object-user and one-element-user-list shapes;
- reject unknown envelope/multi-user nested list;
- optional/missing fields remain missing;
- zero remains real zero;
- fractional `hours` preserved without rounding;
- date-time offset preserved;
- balance current-user and explicit `userId` requests;
- malformed response/schema drift;
- no extra provider request.

Update docs/API_CONTRACT.md and README usage.

No ERP logic.
No real network.
Do not run tests in this handoff under the ERP TSA execution policy.
Stop after source/test-code/docs are written.
