<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class Task extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $name,
        public ?string $projectId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::requiredId($data, 'id'),
            self::nullableString($data, 'name'),
            self::nullableId($data, 'projectId'),
        );
    }
}
