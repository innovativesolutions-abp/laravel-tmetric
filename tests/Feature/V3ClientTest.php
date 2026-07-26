<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use DateTimeImmutable;
use InnovativeSolutions\TMetric\Data\TimeEntry;
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
                'timeZone' => 'Europe/Warsaw',
                'activeAccountId' => 42001,
            ],
            [[
                'id' => 7001,
                'startTime' => '2026-03-28T22:30:00+01:00',
                'endTime' => null,
                'note' => 'Synthetic entry',
                'project' => ['id' => 9001, 'name' => 'Example project'],
                'task' => ['id' => 8001, 'name' => 'EX-1'],
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
        self::assertCount(1, $entries);
        self::assertInstanceOf(TimeEntry::class, $entries->all()[0]);
        self::assertNull($entries->all()[0]->endTime);
        self::assertSame('9001', $entries->all()[0]->projectId);

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'time_entries.list'
                && $request->query['userId'] === '101'
                && $request->query['startDate'] === '2026-03-28'
                && $request->query['endDate'] === '2026-03-29',
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
