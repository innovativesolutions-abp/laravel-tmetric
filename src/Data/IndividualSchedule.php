<?php

namespace InnovativeSolutions\TMetric\Data;

use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;

final readonly class IndividualSchedule extends DataObject
{
    /**
     * @param array<string, mixed> $raw
     * @param list<IndividualScheduleDay>|null $days
     */
    public function __construct(
        array $raw,
        public ?UserBasic $user,
        public ?array $days,
    ) {
        parent::__construct($raw);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data,
            self::user($data),
            self::days($data),
        );
    }

    /** @param array<string, mixed> $data */
    private static function user(array $data): ?UserBasic
    {
        if (! array_key_exists('user', $data)) {
            return null;
        }

        $user = $data['user'];

        if (! is_array($user)) {
            throw new SchemaDriftException('TMetric response field [user] must be an object or one-element list when present.');
        }

        if (array_is_list($user)) {
            if (count($user) !== 1 || ! is_array($user[0]) || array_is_list($user[0])) {
                throw new SchemaDriftException('TMetric response field [user] list must contain exactly one object.');
            }

            $user = $user[0];
        }

        return UserBasic::fromArray($user);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<IndividualScheduleDay>|null
     */
    private static function days(array $data): ?array
    {
        if (! array_key_exists('days', $data)) {
            return null;
        }

        $days = $data['days'];

        if (! is_array($days) || ! array_is_list($days)) {
            throw new SchemaDriftException('TMetric response field [days] must be a list when present.');
        }

        $result = [];

        foreach ($days as $day) {
            if (! is_array($day) || array_is_list($day)) {
                throw new SchemaDriftException('TMetric schedule [days] contains a non-object item.');
            }

            $result[] = IndividualScheduleDay::fromArray($day);
        }

        return $result;
    }
}
