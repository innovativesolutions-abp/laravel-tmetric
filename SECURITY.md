# Security Policy

## Reporting a vulnerability

Report vulnerabilities privately to the repository maintainers. Do not open a public issue containing credentials, Authorization headers, personal activity data, or real TMetric payloads.

## Credential handling

- Store tokens in the consuming application's protected secret environment.
- Do not put tokens in source code, published configuration, queue payloads, fixtures, logs, or exception messages.
- This package keeps a token only in lifetime-bounded runtime connection
  configuration and redacts it from debug context.
- Store any configured SOCKS proxy URI in protected configuration. Proxy URIs
  with credentials are rejected.
- Proxy, connection, and manager configuration is redacted from debug output
  and cannot be serialized.
- Redirect following is disabled so a Bearer token is not forwarded to another host.

## Egress policy

Connections can use a `socks5h://host:port` proxy. When configured, the HTTP
transport uses that proxy on every bounded retry and never retries the package
request directly. The generic package does not require a proxy for every
consumer and does not prohibit a consuming application or unrelated HTTP client
from using the host's ordinary network route. The consuming application is
responsible for making proxy use mandatory when required, isolating the proxy
endpoint, restricting its destinations, and providing any network-level egress
enforcement.

## External requests

Package tests use synthetic fixtures and block unplanned HTTP requests. A real TMetric integration test must be explicitly authorized, use a dedicated read-only test workspace and token, and run outside the standard test suite.

## Privacy

Timeline DTOs expose numerical activity only. Process names and window titles are removed. The package does not persist responses; consumers remain responsible for data minimization, authorization, audit, and retention.
