# Changelog

All notable changes will be documented in this file.

## Unreleased

- Added a typed read-only v3 client for user, client, task, project-for-time-entry, time-entry, latest-entry, and tracking-status reads.
- Added an isolated, opt-in legacy v2 client for detailed reports, Timeline numeric activity, and time entries with deleted-record support.
- Added bounded retries, typed exceptions, token redaction, fail-closed fakes, synthetic fixtures, and Laravel package discovery.
- Documented the official schema evidence and the real-workspace validation still required before a stable release.
- Set the initial supported framework baseline to Laravel 12.
