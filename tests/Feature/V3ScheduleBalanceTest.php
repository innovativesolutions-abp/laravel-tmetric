<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use DateTimeImmutable;
use InnovativeSolutions\TMetric\Data\IndividualSchedule;
use InnovativeSolutions\TMetric\Data\TimeBalanceSummary;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use InnovativeSolutions\TMetric\Facades\TMetric;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Tests\TestCase;

final class V3ScheduleBalanceTest extends TestCase
{
    public function test_schedule_maps_single_object_root_and_uses_exact_bounded_query(): void
    {
        $fake = TMetric::fake([[
            'user' => ['id' => 101, 'name' => 'Synthetic Developer'],
            'days' => [[
                'date' => '2026-09-27T00:00:00+02:00',
                'isWorking' => true,
                'hours' => 6,
            ]],
        ]]);

        $schedules = TMetric::connection()->v3()->schedules(
            new DateTimeImmutable('2026-09-27'),
            new DateTimeImmutable('2026-10-03'),
        );

        self::assertCount(1, $schedules);
        self::assertInstanceOf(IndividualSchedule::class, $schedules->all()[0]);
        self::assertSame('101', $schedules->all()[0]->user?->id);
        $day = $schedules->all()[0]->days[0] ?? null;
        self::assertNotNull($day);
        self::assertSame('2026-09-27T00:00:00+02:00', $day->date);
        self::assertSame(6, $day->hours);

        $fake->assertRequestCount(1);
        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'schedule.list'
                && $request->method === 'GET'
                && $request->path === '/accounts/42001/schedule'
                && $request->query === [
                    'StartDate' => '2026-09-27',
                    'EndDate' => '2026-10-03',
                ]
                && $request->retryTransient === true,
        );
    }

    public function test_schedule_maps_list_root_and_one_element_user_list_without_rounding(): void
    {
        $fake = TMetric::fake([[
            [
                'user' => [['id' => 101, 'name' => 'Synthetic Developer']],
                'days' => [[
                    'date' => '2026-09-28T00:00:00+05:30',
                    'isWorking' => true,
                    'hours' => 6.125,
                ]],
            ],
            [
                'user' => ['id' => 102, 'name' => 'Synthetic Reviewer'],
                'days' => [],
            ],
        ]]);

        $schedules = TMetric::connection()->v3()->schedules(
            new DateTimeImmutable('2026-09-28'),
            new DateTimeImmutable('2026-09-29'),
        );

        self::assertCount(2, $schedules);
        self::assertSame('101', $schedules->all()[0]->user?->id);
        $day = $schedules->all()[0]->days[0] ?? null;
        self::assertNotNull($day);
        self::assertSame(6.125, $day->hours);
        self::assertSame('2026-09-28T00:00:00+05:30', $day->date);
        self::assertSame([], $schedules->all()[1]->days);
        $fake->assertRequestCount(1);
    }

    public function test_schedule_preserves_missing_fields_and_real_zero_values(): void
    {
        TMetric::fake([[
            [
                'user' => ['id' => 101],
            ],
            [
                'user' => ['id' => 102],
                'days' => [[
                    'date' => '2026-09-28T00:00:00Z',
                    'isWorking' => false,
                    'hours' => 0,
                ]],
            ],
        ]]);

        $schedules = TMetric::connection()->v3()->schedules(
            new DateTimeImmutable('2026-09-28'),
            new DateTimeImmutable('2026-09-28'),
        );

        self::assertNull($schedules->all()[0]->days);
        self::assertFalse(array_key_exists('days', $schedules->all()[0]->raw()));

        $day = $schedules->all()[1]->days[0] ?? null;
        self::assertNotNull($day);
        self::assertFalse($day->isWorking);
        self::assertSame(0, $day->hours);
        self::assertTrue(array_key_exists('hours', $day->raw()));
    }

    public function test_schedule_rejects_undocumented_root_envelope(): void
    {
        TMetric::fake([[
            'items' => [[
                'user' => ['id' => 101],
                'days' => [],
            ]],
        ]]);

        $this->expectException(SchemaDriftException::class);

        TMetric::connection()->v3()->schedules(
            new DateTimeImmutable('2026-09-28'),
            new DateTimeImmutable('2026-09-29'),
        );
    }

    public function test_schedule_rejects_multi_user_nested_list(): void
    {
        TMetric::fake([[
            'user' => [
                ['id' => 101],
                ['id' => 102],
            ],
            'days' => [],
        ]]);

        $this->expectException(SchemaDriftException::class);

        TMetric::connection()->v3()->schedules(
            new DateTimeImmutable('2026-09-28'),
            new DateTimeImmutable('2026-09-29'),
        );
    }

    public function test_schedule_rejects_reversed_dates_before_transport(): void
    {
        $fake = TMetric::fake();

        try {
            TMetric::connection()->v3()->schedules(
                new DateTimeImmutable('2026-09-30'),
                new DateTimeImmutable('2026-09-28'),
            );
            self::fail('Expected schedule range validation failure.');
        } catch (ConfigurationException) {
            self::addToAssertionCount(1);
        }

        $fake->assertRequestCount(0);
    }

    public function test_time_balance_current_user_is_one_request_and_preserves_missing_periods(): void
    {
        $fake = TMetric::fake([[
            'today' => [
                'requiredSeconds' => 21600,
                'actualSeconds' => 0,
            ],
        ]]);

        $balance = TMetric::connection()->v3()->timeBalance();

        self::assertInstanceOf(TimeBalanceSummary::class, $balance);
        self::assertNull($balance->month);
        self::assertNull($balance->week);
        self::assertSame(21600, $balance->today?->requiredSeconds);
        self::assertSame(0, $balance->today?->actualSeconds);
        self::assertNull($balance->today?->actualSecondsRounded);
        self::assertFalse(array_key_exists('actualSecondsRounded', $balance->today?->raw() ?? []));

        $fake->assertRequestCount(1);
        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'balance.get'
                && $request->method === 'GET'
                && $request->path === '/accounts/42001/balance'
                && $request->query === []
                && $request->retryTransient === true,
        );
    }

    public function test_time_balance_explicit_user_uses_only_user_id_query(): void
    {
        $fake = TMetric::fake([[
            'month' => [
                'requiredSeconds' => 403200,
                'actualSeconds' => 388800,
                'actualSecondsRounded' => 388800,
            ],
            'today' => [],
            'week' => [],
        ]]);

        $balance = TMetric::connection()->v3()->timeBalance('101');

        self::assertSame(403200, $balance->month?->requiredSeconds);
        self::assertSame([], $balance->today?->raw());
        $fake->assertRequestCount(1);

        TMetric::assertRequested(
            fn (Request $request): bool => $request->operation === 'balance.get'
                && $request->path === '/accounts/42001/balance'
                && $request->query === ['userId' => '101'],
        );
    }

    public function test_time_balance_rejects_non_positive_or_non_integer_user_id_before_transport(): void
    {
        foreach ([0, -1, '0', '-1', '1.5', 'abc', ''] as $invalid) {
            $fake = TMetric::fake();

            try {
                TMetric::connection()->v3()->timeBalance($invalid);
                self::fail('Expected balance userId validation failure.');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }

            $fake->assertRequestCount(0);
        }
    }

    public function test_time_balance_rejects_list_root_and_null_period_values(): void
    {
        TMetric::fake([[[
            'requiredSeconds' => 1,
        ]]]);

        try {
            TMetric::connection()->v3()->timeBalance();
            self::fail('Expected balance root schema drift.');
        } catch (SchemaDriftException) {
            self::addToAssertionCount(1);
        }

        TMetric::fake([['today' => null]]);

        $this->expectException(SchemaDriftException::class);

        TMetric::connection()->v3()->timeBalance();
    }
}
