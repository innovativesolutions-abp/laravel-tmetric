<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class ProvisionedClient extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public string $name,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? $data['clientId'] ?? null;
        $name = $data['name'] ?? $data['clientName'] ?? null;

        if ((! is_int($id) && ! is_string($id)) || trim((string) $id) === '') {
            throw new SchemaDriftException('TMetric created client response must contain an ID.');
        }
        if (! is_string($name) || trim($name) === '') {
            throw new SchemaDriftException('TMetric created client response must contain a name.');
        }

        return new self($data, (string) $id, $name);
    }
}
