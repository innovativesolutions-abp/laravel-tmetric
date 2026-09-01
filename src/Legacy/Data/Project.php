<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataCollection;
use InnovativeSolutions\TMetric\Data\DataObject;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class Project extends DataObject
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  DataCollection<ProjectMember>  $members
     */
    public function __construct(
        array $raw,
        public string $id,
        public string $accountId,
        public ?string $name,
        public ?string $clientId,
        public DataCollection $members,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $members = $data['members'] ?? [];

        if ($members !== null && (! is_array($members) || ! array_is_list($members))) {
            throw new SchemaDriftException('TMetric project members must be a list or null.');
        }

        return new self(
            $data,
            self::requiredNonEmptyId($data, 'projectId'),
            self::requiredNonEmptyId($data, 'accountId'),
            self::nullableString($data, 'projectName'),
            self::nullableId($data, 'clientId'),
            DataCollection::fromRows($members ?? [], ProjectMember::fromArray(...)),
        );
    }
}
