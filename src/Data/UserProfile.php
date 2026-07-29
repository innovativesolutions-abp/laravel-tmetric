<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

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
            self::timeZoneId($data),
            self::nullableId($data, 'activeAccountId'),
        );
    }

    /** @param array<string, mixed> $data */
    private static function timeZoneId(array $data): ?string
    {
        $timeZone = $data['timeZone'] ?? null;

        if ($timeZone === null || is_string($timeZone)) {
            return $timeZone;
        }

        if (! is_array($timeZone) || array_is_list($timeZone)) {
            throw new SchemaDriftException(
                'TMetric response field [timeZone] must be a string, object, or null.',
            );
        }

        $id = $timeZone['id'] ?? null;

        if ($id !== null && ! is_string($id)) {
            throw new SchemaDriftException(
                'TMetric response field [timeZone.id] must be a string or null.',
            );
        }

        return $id;
    }
}
