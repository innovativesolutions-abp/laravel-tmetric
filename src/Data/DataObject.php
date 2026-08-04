<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

abstract readonly class DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(protected array $raw) {}

    /** @return array<string, mixed> */
    final public function raw(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data */
    final protected static function requiredId(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (! is_int($value) && ! is_string($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be an ID.");
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $data */
    final protected static function requiredNonEmptyId(array $data, string $field): string
    {
        $value = self::requiredId($data, $field);

        if (trim($value) === '') {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a non-empty ID.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    final protected static function nullableId(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! is_string($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be an ID or null.");
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $data */
    final protected static function nullableString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a string or null.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    final protected static function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a non-empty string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    final protected static function nullableInt(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        if ($value !== null && ! is_int($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be an integer or null.");
        }

        return $value;
    }
}
