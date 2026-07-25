<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataObject;

final readonly class DetailedReportRow extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $day,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $userProfileId,
        public ?string $projectId,
        public ?string $clientId,
        public ?string $projectTaskId,
        public ?string $issueId,
        public ?string $issueUrl,
        public ?string $description,
        public ?int $duration,
        public ?int $billableDuration,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::nullableString($data, 'day'),
            self::nullableString($data, 'startTime'),
            self::nullableString($data, 'endTime'),
            self::nullableId($data, 'userProfileId'),
            self::nullableId($data, 'projectId'),
            self::nullableId($data, 'clientId'),
            self::nullableId($data, 'projectTaskId'),
            self::nullableId($data, 'issueId'),
            self::nullableString($data, 'issueUrl'),
            self::nullableString($data, 'description'),
            self::nullableInt($data, 'duration'),
            self::nullableInt($data, 'billableDuration'),
        );
    }
}
