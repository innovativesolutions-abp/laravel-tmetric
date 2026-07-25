<?php

namespace InnovativeSolutions\TMetric\Testing;

use JsonException;
use RuntimeException;

final class Fixture
{
    /** @return array<mixed> */
    public static function load(string $name): array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            throw new RuntimeException('Invalid TMetric fixture name.');
        }

        $path = __DIR__."/../../resources/fixtures/{$name}.json";

        if (! is_file($path)) {
            throw new RuntimeException("TMetric fixture [{$name}] does not exist.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("TMetric fixture [{$name}] is invalid.", 0, $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException("TMetric fixture [{$name}] must contain a JSON object or array.");
        }

        return $data;
    }
}
