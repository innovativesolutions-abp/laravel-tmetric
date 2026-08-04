<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class TaskBasic extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $name,
        public ?ExternalLink $externalLink,
        public ?IntegrationBasic $integration,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $externalLink = is_array($data['externalLink'] ?? null)
            ? ExternalLink::fromArray($data['externalLink'])
            : null;
        $integration = is_array($data['integration'] ?? null)
            ? IntegrationBasic::fromArray($data['integration'])
            : null;

        return new self(
            $data,
            self::requiredId($data, 'id'),
            self::nullableString($data, 'name'),
            $externalLink,
            $integration,
        );
    }
}
