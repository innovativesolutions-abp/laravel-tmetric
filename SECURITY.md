# Security Policy

## Reporting a vulnerability

Report vulnerabilities privately to the repository maintainers. Do not open a public issue containing credentials, Authorization headers, personal activity data, or real TMetric payloads.

## Credential handling

- Store tokens in the consuming application's protected secret environment.
- Do not put tokens in source code, published configuration, queue payloads, fixtures, logs, or exception messages.
- This package keeps a token in memory only for the duration of a request and redacts it from debug context.
- Redirect following is disabled so a Bearer token is not forwarded to another host.

## External requests

Package tests use synthetic fixtures and block unplanned HTTP requests. A real TMetric integration test must be explicitly authorized, use a dedicated read-only test workspace and token, and run outside the standard test suite.

## Privacy

Timeline DTOs expose numerical activity only. Process names and window titles are removed. The package does not persist responses; consumers remain responsible for data minimization, authorization, audit, and retention.
