<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class IndividualScheduleDay extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $date,
        public ?bool $isWorking,
        public int|float|null $hours,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::optionalString($data, 'date'),
            self::optionalBool($data, 'isWorking'),
            self::optionalNumber($data, 'hours'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $field): ?string
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        if (! is_string($data[$field])) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a string when present.");
        }

        return $data[$field];
    }

    /** @param array<string, mixed> $data */
    private static function optionalBool(array $data, string $field): ?bool
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        if (! is_bool($data[$field])) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a boolean when present.");
        }

        return $data[$field];
    }

    /** @param array<string, mixed> $data */
    private static function optionalNumber(array $data, string $field): int|float|null
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        $value = $data[$field];

        if (! is_int($value) && ! is_float($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a number when present.");
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be a finite number.");
        }

        return $value;
    }
}
