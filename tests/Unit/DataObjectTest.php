<?php

namespace InnovativeSolutions\TMetric\Tests\Unit;

use InnovativeSolutions\TMetric\Data\TimeEntry;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Tests\TestCase;

final class DataObjectTest extends TestCase
{
    public function test_ids_are_normalized_to_strings_and_unknown_fields_are_preserved(): void
    {
        $entry = TimeEntry::fromArray([
            'id' => 7001,
            'startTime' => '2026-07-25T08:00:00Z',
            'endTime' => '2026-07-25T09:00:00Z',
            'futureField' => ['supported' => 'through raw escape hatch'],
        ]);

        self::assertSame('7001', $entry->id);
        self::assertSame(['supported' => 'through raw escape hatch'], $entry->raw()['futureField']);
    }

    public function test_missing_required_id_is_reported_as_schema_drift(): void
    {
        $this->expectException(SchemaDriftException::class);

        TimeEntry::fromArray(['startTime' => '2026-07-25T08:00:00Z']);
    }

    public function test_connection_debug_output_redacts_token(): void
    {
        $connection = ConnectionConfig::fromArray('default', config('tmetric.connections.default'));
        $debug = $connection->__debugInfo();

        self::assertSame('[REDACTED]', $debug['token']);
        self::assertStringNotContainsString('synthetic-secret-token', serialize($debug));
    }

    public function test_connection_configuration_cannot_be_serialized_into_a_queue_payload(): void
    {
        $connection = ConnectionConfig::fromArray('default', config('tmetric.connections.default'));

        $this->expectException(\LogicException::class);

        serialize($connection);
    }

    public function test_http_url_cannot_bypass_https_validation_with_a_test_substring(): void
    {
        $config = config('tmetric.connections.default');
        $config['v3_base_url'] = 'http://evil.example/path/.test';

        $this->expectException(ConfigurationException::class);

        ConnectionConfig::fromArray('default', $config);
    }
}
