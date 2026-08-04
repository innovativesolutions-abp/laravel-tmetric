<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class IntegrationBasic extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $id,
        public ?string $url,
        public ?string $type,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::nullableId($data, 'id'),
            self::nullableString($data, 'url'),
            self::nullableString($data, 'type'),
        );
    }
}
