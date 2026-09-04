<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use DateTimeImmutable;
use InnovativeSolutions\TMetric\Exceptions\LegacyApiDisabledException;
use InnovativeSolutions\TMetric\Facades\TMetric;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Legacy\Data\Project;
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

    public function test_it_reads_a_full_project_and_adds_one_member_without_dropping_fields(): void
    {
        $project = [
            'projectId' => '9001',
            'accountId' => '42001',
            'projectName' => 'Data Plans',
            'projectStatus' => 1,
            'isBillable' => true,
            'notes' => 'Preserve every field',
            'members' => [[
                'userProfileId' => '101',
                'projectId' => '9001',
                'role' => 1,
            ]],
            'groups' => [['projectId' => '9001', 'userGroupId' => '55']],
        ];
        TMetric::fake([$project, [
            ...$project,
            'members' => [
                ...$project['members'],
                ['userProfileId' => 102, 'projectId' => 9001, 'role' => 0],
            ],
        ]]);

        $current = TMetric::connection()->legacy()->project(9001);
        $updated = TMetric::connection()->legacy()->addProjectMember($current, 102);

        self::assertCount(2, $updated->members);
        self::assertSame('55', $current->groups->all()[0]->userGroupId);
        TMetric::assertRequested(fn (Request $request): bool => $request->operation === 'legacy.projects.get'
            && $request->method === 'GET'
            && $request->path === '/api/accounts/42001/projects/9001');
        TMetric::assertRequested(fn (Request $request): bool => $request->operation === 'legacy.projects.add_member'
            && $request->method === 'PUT'
            && $request->legacy
            && $request->retryTransient === false
            && $request->body['projectId'] === 9001
            && $request->body['accountId'] === 42001
            && $request->body['notes'] === 'Preserve every field'
            && $request->body['groups'] === [['projectId' => 9001, 'userGroupId' => 55]]
            && $request->body['members'][0] === [
                'userProfileId' => 101,
                'projectId' => 9001,
                'role' => 1,
            ]
            && $request->body['members'][1] === [
                'userProfileId' => 102,
                'projectId' => 9001,
                'role' => 0,
            ]);
    }

    public function test_it_reads_members_and_supervisors_of_a_project_user_group(): void
    {
        TMetric::fake([[
            'userGroupId' => 55,
            'accountId' => 42001,
            'name' => 'ConnectUs',
            'members' => [[
                'userProfileId' => 102,
                'userGroupId' => 55,
                'accountId' => 42001,
            ]],
            'supervisors' => [[
                'userProfileId' => 103,
                'userGroupId' => 55,
                'accountId' => 42001,
            ]],
        ]]);

        $group = TMetric::connection()->legacy()->userGroup(55);

        self::assertSame('55', $group->id);
        self::assertSame('42001', $group->accountId);
        self::assertSame('ConnectUs', $group->name);
        self::assertSame('102', $group->members->all()[0]->userProfileId);
        self::assertSame('103', $group->supervisors->all()[0]->userProfileId);
        TMetric::assertRequested(fn (Request $request): bool => $request->operation === 'legacy.user_groups.get'
            && $request->method === 'GET'
            && $request->legacy
            && $request->path === '/api/accounts/42001/usergroups/55');
    }

    public function test_adding_an_existing_project_member_is_idempotent_and_sends_no_request(): void
    {
        $project = Project::fromArray([
            'projectId' => 9001,
            'accountId' => 42001,
            'projectName' => 'Data Plans',
            'members' => [[
                'userProfileId' => 101,
                'projectId' => 9001,
                'role' => 0,
            ]],
        ]);
        $transport = new FakeTransport;
        $client = new LegacyV2Client(
            ConnectionConfig::fromArray('default', config('tmetric.connections.default')),
            $transport,
        );

        $unchanged = $client->addProjectMember($project, 101);

        self::assertSame($project, $unchanged);
        self::assertCount(0, $transport->recorded());
    }
}
