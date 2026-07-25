<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataCollection;
use InnovativeSolutions\TMetric\Data\DataObject;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class TimelineEntry extends DataObject
{
    /** @param array<string, mixed> $raw @param DataCollection<TimelineSegment> $segments */
    public function __construct(
        array $raw,
        public ?string $startTime,
        public DataCollection $segments,
    ) {
        parent::__construct([
            'startTime' => $this->startTime,
            'details' => array_map(
                static fn (TimelineSegment $segment): array => $segment->raw(),
                $this->segments->all(),
            ),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $details = $data['details'] ?? [];

        if (! is_array($details)) {
            throw new SchemaDriftException('TMetric Timeline details must be an array.');
        }

        return new self(
            $data,
            self::nullableString($data, 'startTime'),
            DataCollection::fromRows($details, TimelineSegment::fromArray(...)),
        );
    }
}
