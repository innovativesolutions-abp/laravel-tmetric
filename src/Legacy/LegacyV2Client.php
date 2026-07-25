<?php

namespace InnovativeSolutions\TMetric\Legacy;

use DateTimeInterface;
use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Data\DataCollection;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Exceptions\LegacyApiDisabledException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Legacy\Data\DetailedReportRow;
use InnovativeSolutions\TMetric\Legacy\Data\LegacyTimeEntry;
use InnovativeSolutions\TMetric\Legacy\Data\TimelineEntry;

final readonly class LegacyV2Client
{
    public function __construct(
        private ConnectionConfig $connection,
        private Transport $transport,
    ) {
        if (! $this->connection->legacyEnabled) {
            throw new LegacyApiDisabledException(
                "Legacy TMetric API is disabled for connection [{$this->connection->name}].",
            );
        }
    }

    /** @param list<string|int> $profileIds @return DataCollection<DetailedReportRow> */
    public function detailedReport(
        DateTimeInterface $start,
        DateTimeInterface $end,
        array $profileIds = [],
    ): DataCollection {
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'legacy.detailed_report',
                'GET',
                '/api/reports/detailed',
                [
                    'AccountId' => $this->rawAccountId(),
                    'ProfileList' => array_map('strval', $profileIds),
                    'StartDate' => $start->format(DATE_ATOM),
                    'EndDate' => $end->format(DATE_ATOM),
                    'UseUtcTime' => true,
                ],
                true,
            ),
        );

        return DataCollection::fromRows($response->data, DetailedReportRow::fromArray(...));
    }

    /** @return DataCollection<TimelineEntry> */
    public function timeline(
        string|int $userProfileId,
        DateTimeInterface $start,
        DateTimeInterface $end,
    ): DataCollection {
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'legacy.timeline',
                'GET',
                "/api/timeline/{$this->accountId()}",
                [
                    'userProfileId' => (string) $userProfileId,
                    'StartTime' => $start->format(DATE_ATOM),
                    'EndTime' => $end->format(DATE_ATOM),
                ],
                true,
            ),
        );

        return DataCollection::fromRows($response->data, TimelineEntry::fromArray(...));
    }

    /** @return DataCollection<LegacyTimeEntry> */
    public function timeEntries(
        string|int $userProfileId,
        DateTimeInterface $start,
        DateTimeInterface $end,
        bool $includeDeleted = true,
    ): DataCollection {
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'legacy.time_entries.list',
                'GET',
                "/api/accounts/{$this->accountId()}/timeentries/".rawurlencode((string) $userProfileId),
                [
                    'StartTime' => $start->format(DATE_ATOM),
                    'EndTime' => $end->format(DATE_ATOM),
                    'useUtcTime' => true,
                    'includeDeleted' => $includeDeleted,
                    'truncate' => false,
                ],
                true,
            ),
        );

        return DataCollection::fromRows($response->data, LegacyTimeEntry::fromArray(...));
    }

    private function rawAccountId(): string
    {
        if ($this->connection->accountId === null || $this->connection->accountId === '') {
            throw new ConfigurationException(
                "TMetric connection [{$this->connection->name}] has no account_id.",
            );
        }

        return $this->connection->accountId;
    }

    private function accountId(): string
    {
        return rawurlencode($this->rawAccountId());
    }
}
