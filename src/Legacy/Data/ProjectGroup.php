<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataObject;

final readonly class ProjectGroup extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $projectId,
        public string $userGroupId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::nullableId($data, 'projectId'),
            self::requiredNonEmptyId($data, 'userGroupId'),
        );
    }
}
