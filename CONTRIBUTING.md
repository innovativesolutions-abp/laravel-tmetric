# Contributing

## Scope

Keep the package a generic, read-only TMetric transport and DTO layer. Jira, calendars, AI classification, synchronization history, persistence, findings, notifications, and ERP policies belong in consuming applications.

Write operations require a separately reviewed release and must not be added as incidental helpers.

## Local checks

```bash
composer install
vendor/bin/pint --test
vendor/bin/phpunit
composer validate --strict
```

Tests must use `Http::fake()`, package `TMetric::fake()`, or synthetic fixtures. Do not use real TMetric tokens or enable real network access in the standard suite.

## Fixtures

Fixtures must be synthetic and contain no real names, email addresses, issue URLs, process names, window titles, tokens, or workspace identifiers.

## Pull requests

Describe the official API evidence behind a changed endpoint or schema. Record the documentation version/date and preserve backward compatibility where possible.
