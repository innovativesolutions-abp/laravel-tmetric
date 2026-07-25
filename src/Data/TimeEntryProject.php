<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class TimeEntryProject extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $name,
        public ?string $clientId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $client = is_array($data['client'] ?? null) ? $data['client'] : [];

        return new self(
            $data,
            self::requiredId($data, 'id'),
            self::nullableString($data, 'name'),
            self::nullableId($client, 'id'),
        );
    }
}
