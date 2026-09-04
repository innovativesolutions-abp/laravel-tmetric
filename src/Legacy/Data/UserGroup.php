<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataCollection;
use InnovativeSolutions\TMetric\Data\DataObject;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class UserGroup extends DataObject
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  DataCollection<UserGroupMember>  $members
     * @param  DataCollection<UserGroupSupervisor>  $supervisors
     */
    public function __construct(
        array $raw,
        public string $id,
        public string $accountId,
        public string $name,
        public DataCollection $members,
        public DataCollection $supervisors,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $members = $data['members'] ?? [];
        $supervisors = $data['supervisors'] ?? [];

        if ($members !== null && (! is_array($members) || ! array_is_list($members))) {
            throw new SchemaDriftException('TMetric user group members must be a list or null.');
        }
        if ($supervisors !== null && (! is_array($supervisors) || ! array_is_list($supervisors))) {
            throw new SchemaDriftException('TMetric user group supervisors must be a list or null.');
        }

        return new self(
            $data,
            self::requiredNonEmptyId($data, 'userGroupId'),
            self::requiredNonEmptyId($data, 'accountId'),
            self::requiredString($data, 'name'),
            DataCollection::fromRows($members ?? [], UserGroupMember::fromArray(...)),
            DataCollection::fromRows($supervisors ?? [], UserGroupSupervisor::fromArray(...)),
        );
    }
}
