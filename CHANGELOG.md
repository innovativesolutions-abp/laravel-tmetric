# Changelog

All notable changes will be documented in this file.

## Unreleased

- Added typed legacy project-group and user-group reads so consumers can
  distinguish direct project members, team members, and team supervisors.
- Added full-body numeric-ID time-entry project updates, typed legacy project
  and member reads, idempotent full-project member addition, and bounded
  sanitized negative-response details.
- Added credential-free HTTP CONNECT proxy support alongside `socks5h`.
- Added optional typed `socks5h` proxy support with remote DNS, no direct retry
  after a proxy is selected, redaction, and serialization guards.
- Added PHP cURL as a runtime requirement and verified that retries preserve
  the selected proxy while redirects remain disabled.
- Added a typed read-only v3 client for user, client, task, project-for-time-entry, time-entry, latest-entry, and tracking-status reads.
- Added an isolated, opt-in legacy v2 client for detailed reports, Timeline numeric activity, and time entries with deleted-record support.
- Added bounded retries, typed exceptions, token redaction, fail-closed fakes, synthetic fixtures, and Laravel package discovery.
- Documented the official schema evidence and the real-workspace validation still required before a stable release.
- Set the initial supported framework baseline to Laravel 12.
