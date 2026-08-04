<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use DateTimeImmutable;
use InnovativeSolutions\TMetric\Data\TimeEntry;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use InnovativeSolutions\TMetric\Exceptions\UnexpectedRequestException;
use InnovativeSolutions\TMetric\Facades\TMetric;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Tests\TestCase;

final class V3ClientTest extends TestCase
{
    public function test_it_maps_user_and_time_entries_using_only_fake_responses(): void
    {
        TMetric::fake([
            [
                'id' => 101,
                'email' => 'developer@example.test',
                'name' => 'Synthetic Developer',
                'timeZone' => [
                    'id' => 'Europe/Warsaw',
                    'displayName' => '(UTC+01:00) Warsaw',
                    'winterOffset' => 60,
                    'summerOffset' => 120,
                    'currentOffset' => 120,
                ],
                'activeAccountId' => 42001,
            ],
            [[
                'id' => 7001,
                'startTime' => '2026-03-28T22:30:00+01:00',
                'endTime' => null,
                'note' => 'Synthetic entry',
                'project' => ['id' => 9001, 'name' => 'Example project'],
                'task' => [
                    'id' => 8001,
                    'name' => 'EX-1',
                    'externalLink' => [
                        'caption' => 'EX-1',
                        'iconUrl' => 'https://jira.example.test/icon.png',
                        'link' => 'https://jira.example.test/browse/EX-1',
                        'issueId' => 'EX-1',
                    ],
                    'integration' => [
                        'id' => 5001,
                        'url' => 'https://jira.example.test',
                        'type' => 'jira',
                    ],
                ],
                'isBillable' => true,
                'isInvoiced' => false,
            ]],
        ]);

        $user = TMetric::connection()->v3()->user();
        $entries = TMetric::connection()->v3()->timeEntries(
            '101',
            new DateTimeImmutable('2026-03-28', new \DateTimeZone('Europe/Warsaw')),
            new DateTimeImmutable('2026-03-29', new \DateTimeZone('Europe/Warsaw')),
        );

        self::assertSame('101', $user->id);
        self::assertSame('42001', $user->activeAccountId);
        self::assertSame('Europe/Warsaw', $user->timeZone);
        self::assertCount(1, $entries);
        self::assertInstanceOf(TimeEntry::class, $entries->all()[0]);
        self::assertNull($entries->all()[0]->endTime);
        self::assertSame('9001', $entries->all()[0]->projectId);
        self::assertSame('EX-1', $entries->all()[0]->task?->externalLink?->issueId);
        self::assertSame('https://jira.example.test', $entries->all()[0]->task?->integration?->url);

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'time_entries.list'
                && $request->query['userId'] === '101'
                && $request->query['startDate'] === '2026-03-28'
                && $request->query['endDate'] === '2026-03-29',
        );
    }

    public function test_it_parses_external_jira_identity_from_workspace_tasks(): void
    {
        TMetric::fake([[[
            'id' => 8001,
            'name' => 'Visible display name is not an identifier',
            'projectId' => 9001,
            'externalLink' => [
                'caption' => 'EX-1',
                'iconUrl' => 'https://jira.example.test/icon.png',
                'link' => 'https://jira.example.test/browse/EX-1',
                'issueId' => 'EX-1',
            ],
            'integration' => [
                'id' => 5001,
                'url' => 'https://jira.example.test',
                'type' => 'jira',
            ],
        ]]]);

        $task = TMetric::connection()->v3()->tasks()->all()[0];

        self::assertSame('EX-1', $task->externalLink?->issueId);
        self::assertSame('https://jira.example.test/browse/EX-1', $task->externalLink?->link);
        self::assertSame('jira', $task->integration?->type);
    }

    public function test_it_rejects_a_blank_external_issue_identity(): void
    {
        TMetric::fake([[['id' => 8001, 'externalLink' => [
            'caption' => 'EX-1',
            'iconUrl' => null,
            'link' => 'https://jira.example.test/browse/EX-1',
            'issueId' => '  ',
        ]]]]);

        $this->expectException(SchemaDriftException::class);

        TMetric::connection()->v3()->tasks();
    }

    public function test_missing_optional_external_identity_remains_compatible(): void
    {
        TMetric::fake([[['id' => 8001, 'name' => 'Internal task', 'projectId' => 9001]]]);

        $task = TMetric::connection()->v3()->tasks()->all()[0];

        self::assertNull($task->externalLink);
        self::assertNull($task->integration);
    }

    public function test_it_updates_a_time_entry_project_without_enabling_transport_retries(): void
    {
        TMetric::fake([[
            'id' => 7001,
            'startTime' => '2026-03-28T22:30:00+01:00',
            'endTime' => '2026-03-28T23:00:00+01:00',
            'project' => ['id' => 9002],
            'task' => ['id' => 8001, 'name' => 'EX-1'],
        ]]);

        $entry = TMetric::connection()->v3()->updateTimeEntryProject('7001', '9002');

        self::assertSame('9002', $entry?->projectId);
        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'time_entries.update_project'
                && $request->method === 'PUT'
                && $request->path === '/accounts/42001/timeentries/7001'
                && $request->body === ['project' => ['id' => '9002']]
                && $request->retryTransient === false,
        );
    }

    public function test_fake_fails_closed_for_an_unplanned_request(): void
    {
        TMetric::fake();

        $this->expectException(UnexpectedRequestException::class);

        TMetric::connection()->v3()->user();
    }

    public function test_fake_supports_a_generic_connection_without_proxy_configuration(): void
    {
        config()->set('tmetric.connections.default.proxy');
        TMetric::fake([['id' => 101]]);

        $profile = TMetric::connection()->v3()->user();

        self::assertSame('101', $profile->id);
    }

    public function test_user_profile_remains_compatible_with_a_string_timezone(): void
    {
        TMetric::fake([[
            'id' => 101,
            'timeZone' => 'Europe/Warsaw',
        ]]);

        $profile = TMetric::connection()->v3()->user();

        self::assertSame('Europe/Warsaw', $profile->timeZone);
    }

    public function test_runtime_connection_can_be_created_for_database_backed_consumers(): void
    {
        TMetric::fake([[
            'id' => 101,
            'email' => 'developer@example.test',
        ]]);

        $profile = TMetric::connect([
            'token' => 'runtime-synthetic-token',
            'account_id' => '42001',
            'proxy' => 'socks5h://tmetric-egress.test:1080',
        ])->v3()->user();

        self::assertSame('101', $profile->id);
    }

    public function test_time_tracking_status_preserves_the_typed_active_timer(): void
    {
        TMetric::fake([[
            [
                'user' => ['id' => 101],
                'activeTimer' => [
                    'id' => 7001,
                    'startTime' => '2026-07-25T08:00:00Z',
                    'endTime' => null,
                    'project' => ['id' => 9001],
                ],
                'startTime' => '2026-07-25T08:00:00Z',
                'finishTime' => null,
                'totalSeconds' => 1800,
            ],
        ]]);

        $status = TMetric::connection()->v3()->timeTrackingStatuses()->all()[0];

        self::assertTrue($status->hasActiveTimer());
        self::assertSame('7001', $status->activeTimer?->id);
        self::assertNull($status->activeTimer?->endTime);
    }

    public function test_it_reads_workspace_users_visible_to_reports(): void
    {
        TMetric::fake([[
            'teams' => [],
            'users' => [
                ['id' => 101, 'name' => 'Synthetic Developer'],
                ['id' => 102, 'name' => 'Synthetic Reviewer'],
            ],
        ]]);

        $users = TMetric::connection()->v3()->reportUsers();

        self::assertCount(2, $users);
        self::assertSame('101', $users->all()[0]->id);
        self::assertSame('Synthetic Reviewer', $users->all()[1]->name);

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'reports.project_filter'
                && $request->path === '/accounts/42001/reports/projects/filter',
        );
    }
}
