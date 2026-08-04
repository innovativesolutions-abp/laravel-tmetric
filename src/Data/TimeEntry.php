<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class TimeEntry extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $startTime,
        public ?string $endTime,
        public ?string $note,
        public bool $billable,
        public bool $invoiced,
        public ?string $projectId,
        public ?string $taskId,
        public ?TaskBasic $task = null,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $project = is_array($data['project'] ?? null) ? $data['project'] : [];
        $task = is_array($data['task'] ?? null) ? $data['task'] : [];

        return new self(
            $data,
            self::requiredId($data, 'id'),
            self::nullableString($data, 'startTime'),
            self::nullableString($data, 'endTime'),
            self::nullableString($data, 'note'),
            (bool) ($data['isBillable'] ?? false),
            (bool) ($data['isInvoiced'] ?? false),
            self::nullableId($project, 'id'),
            self::nullableId($task, 'id'),
            $task === [] ? null : TaskBasic::fromArray($task),
        );
    }
}
