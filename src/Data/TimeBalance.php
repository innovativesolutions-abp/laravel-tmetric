<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class TimeBalance extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?int $requiredSeconds,
        public ?int $actualSeconds,
        public ?int $actualSecondsRounded,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::optionalInt($data, 'requiredSeconds'),
            self::optionalInt($data, 'actualSeconds'),
            self::optionalInt($data, 'actualSecondsRounded'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function optionalInt(array $data, string $field): ?int
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        if (! is_int($data[$field])) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be an integer when present.");
        }

        return $data[$field];
    }
}
