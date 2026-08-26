<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class ProvisionedProject extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public string $name,
        public ?string $clientId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? $data['projectId'] ?? null;
        $name = $data['name'] ?? $data['projectName'] ?? null;
        $client = is_array($data['client'] ?? null) ? $data['client'] : [];
        $clientId = $data['clientId'] ?? $client['id'] ?? null;

        if ((! is_int($id) && ! is_string($id)) || trim((string) $id) === '') {
            throw new SchemaDriftException('TMetric created project response must contain an ID.');
        }
        if (! is_string($name) || trim($name) === '') {
            throw new SchemaDriftException('TMetric created project response must contain a name.');
        }
        if ($clientId !== null && ! is_int($clientId) && ! is_string($clientId)) {
            throw new SchemaDriftException('TMetric created project client ID must be an ID or null.');
        }

        return new self($data, (string) $id, $name, $clientId === null ? null : (string) $clientId);
    }
}
