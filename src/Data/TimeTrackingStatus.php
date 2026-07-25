<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class TimeTrackingStatus extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $userId,
        public ?string $startTime,
        public ?string $finishTime,
        public ?int $totalSeconds,
        public ?TimeEntry $activeTimer,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $user = is_array($data['user'] ?? null) ? $data['user'] : [];
        $activeTimer = $data['activeTimer'] ?? null;

        if ($activeTimer !== null && ! is_array($activeTimer)) {
            throw new SchemaDriftException(
                'TMetric activeTimer must be an object or null.',
            );
        }

        return new self(
            $data,
            self::nullableId($user, 'id'),
            self::nullableString($data, 'startTime'),
            self::nullableString($data, 'finishTime'),
            self::nullableInt($data, 'totalSeconds'),
            $activeTimer === null ? null : TimeEntry::fromArray($activeTimer),
        );
    }

    public function hasActiveTimer(): bool
    {
        return $this->activeTimer !== null;
    }
}
