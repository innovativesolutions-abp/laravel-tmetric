<?php

namespace InnovativeSolutions\TMetric\Data;

final readonly class UserProfile extends DataObject
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        array $raw,
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $timeZone,
        public ?string $activeAccountId,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::requiredId($data, 'id'),
            self::nullableString($data, 'email'),
            self::nullableString($data, 'name'),
            self::nullableString($data, 'timeZone'),
            self::nullableId($data, 'activeAccountId'),
        );
    }
}
