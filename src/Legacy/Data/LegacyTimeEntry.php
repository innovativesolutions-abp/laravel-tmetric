<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataObject;

final readonly class LegacyTimeEntry extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $startTime,
        public ?string $endTime,
        public bool $deleted,
        public ?int $timerDuration,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::requiredId($data, 'timeEntryId'),
            self::nullableString($data, 'startTime'),
            self::nullableString($data, 'endTime'),
            (bool) ($data['isDeleted'] ?? false),
            self::nullableInt($data, 'timerDuration'),
        );
    }
}
