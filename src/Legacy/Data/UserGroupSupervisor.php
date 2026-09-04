<?php

namespace InnovativeSolutions\TMetric\Legacy\Data;

use InnovativeSolutions\TMetric\Data\DataObject;

final readonly class UserGroupSupervisor extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $userProfileId,
        public ?string $userGroupId,
        public ?string $accountId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::requiredNonEmptyId($data, 'userProfileId'),
            self::nullableId($data, 'userGroupId'),
            self::nullableId($data, 'accountId'),
        );
    }
}
