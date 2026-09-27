<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class TimeBalanceSummary extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?TimeBalance $month,
        public ?TimeBalance $today,
        public ?TimeBalance $week,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::period($data, 'month'),
            self::period($data, 'today'),
            self::period($data, 'week'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function period(array $data, string $field): ?TimeBalance
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        $value = $data[$field];

        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new SchemaDriftException("TMetric response field [{$field}] must be an object when present.");
        }

        return TimeBalance::fromArray($value);
    }
}
