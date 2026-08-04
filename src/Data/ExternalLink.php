<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class ExternalLink extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public ?string $caption,
        public ?string $iconUrl,
        public string $link,
        public string $issueId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::nullableString($data, 'caption'),
            self::nullableString($data, 'iconUrl'),
            self::requiredString($data, 'link'),
            self::requiredNonEmptyId($data, 'issueId'),
        );
    }
}
