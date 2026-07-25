<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use DateTimeImmutable;
use InnovativeSolutions\TMetric\Exceptions\LegacyApiDisabledException;
use InnovativeSolutions\TMetric\Facades\TMetric;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Legacy\LegacyV2Client;
use InnovativeSolutions\TMetric\Testing\FakeTransport;
use InnovativeSolutions\TMetric\Tests\TestCase;

final class LegacyV2ClientTest extends TestCase
{
    public function test_it_reads_detailed_report_and_timeline_without_exposing_window_details(): void
    {
        TMetric::fake([
            [[
                'day' => '2026-07-25',
                'startTime' => '2026-07-25T08:00:00Z',
                'endTime' => '2026-07-25T09:00:00Z',
                'userProfileId' => 101,
                'projectId' => 9001,
                'clientId' => 5001,
                'projectTaskId' => 8001,
                'issueId' => 'EX-1',
                'issueUrl' => 'https://jira.example.test/browse/EX-1',
                'description' => 'Synthetic work',
                'duration' => 3600,
                'billableDuration' => 3600,
            ]],
            [[
                'startTime' => '2026-07-25T08:00:00Z',
                'details' => [[
                    'activitySeconds' => 540,
                    'totalSeconds' => 600,
                    'timelineProcess' => 'must-not-leave-the-transport',
                    'timelineWindow' => 'must-not-leave-the-transport',
                    'futureSensitiveField' => [
                        'applicationName' => 'must-not-leave-the-transport',
                    ],
                ]],
                'futureTopLevelActivityMetadata' => 'must-not-leave-the-transport',
            ]],
        ]);

        $start = new DateTimeImmutable('2026-07-25T00:00:00Z');
        $end = new DateTimeImmutable('2026-07-26T00:00:00Z');
        $report = TMetric::connection()->legacy()->detailedReport($start, $end, ['101']);
        $timeline = TMetric::connection()->legacy()->timeline('101', $start, $end);

        self::assertSame('EX-1', $report->all()[0]->issueId);
        self::assertSame(540, $timeline->all()[0]->segments->all()[0]->activitySeconds);
        self::assertArrayNotHasKey('timelineProcess', $timeline->all()[0]->raw()['details'][0]);
        self::assertArrayNotHasKey('timelineWindow', $timeline->all()[0]->segments->all()[0]->raw());
        self::assertArrayNotHasKey('futureSensitiveField', $timeline->all()[0]->raw()['details'][0]);
        self::assertArrayNotHasKey('futureTopLevelActivityMetadata', $timeline->all()[0]->raw());

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'legacy.timeline'
                && $request->legacy
                && $request->query['userProfileId'] === '101',
        );
    }

    public function test_legacy_time_entries_request_deleted_records_explicitly(): void
    {
        TMetric::fake([[
            [
                'timeEntryId' => 7001,
                'startTime' => '2026-07-25T08:00:00Z',
                'endTime' => '2026-07-25T09:00:00Z',
                'isDeleted' => true,
                'timerDuration' => 3600,
            ],
        ]]);

        TMetric::connection()->legacy()->timeEntries(
            101,
            new DateTimeImmutable('2026-07-25T00:00:00Z'),
            new DateTimeImmutable('2026-07-26T00:00:00Z'),
        );

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'legacy.time_entries.list'
                && $request->query['includeDeleted'] === true
                && $request->query['truncate'] === false,
        );
        self::addToAssertionCount(1);
    }

    public function test_legacy_client_cannot_bypass_the_connection_feature_flag(): void
    {
        $config = config('tmetric.connections.default');
        $config['legacy_enabled'] = false;

        $this->expectException(LegacyApiDisabledException::class);

        new LegacyV2Client(ConnectionConfig::fromArray('default', $config), new FakeTransport);
    }
}
