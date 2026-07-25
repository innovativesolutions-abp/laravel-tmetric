<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataObject;

final readonly class TimelineSegment extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?int $activitySeconds,
        public ?int $totalSeconds,
    ) {
        parent::__construct([
            'activitySeconds' => $this->activitySeconds,
            'totalSeconds' => $this->totalSeconds,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::nullableInt($data, 'activitySeconds'),
            self::nullableInt($data, 'totalSeconds'),
        );
    }
}
