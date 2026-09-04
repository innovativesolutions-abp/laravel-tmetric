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
use InnovativeSolutions\TMetric\Legacy\Data\Project;
use InnovativeSolutions\TMetric\Legacy\Data\TimelineEntry;
use InnovativeSolutions\TMetric\Legacy\Data\UserGroup;

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

    public function project(string|int $projectId): Project
    {
        $id = $this->numericId($projectId, 'projectId');
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'legacy.projects.get',
                'GET',
                "/api/accounts/{$this->accountId()}/projects/{$id}",
                legacy: true,
            ),
        );

        return Project::fromArray($response->data);
    }

    public function userGroup(string|int $userGroupId): UserGroup
    {
        $id = $this->numericId($userGroupId, 'userGroupId');
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'legacy.user_groups.get',
                'GET',
                "/api/accounts/{$this->accountId()}/usergroups/{$id}",
                legacy: true,
            ),
        );

        return UserGroup::fromArray($response->data);
    }

    public function addProjectMember(Project $project, string|int $userProfileId, int $role = 0): Project
    {
        return $this->updateProjectMembers(
            $project,
            [$userProfileId],
            $role,
            'legacy.projects.add_member',
        );
    }

    /** @param list<string|int> $userProfileIds */
    public function addProjectMembers(Project $project, array $userProfileIds, int $role = 0): Project
    {
        return $this->updateProjectMembers(
            $project,
            $userProfileIds,
            $role,
            'legacy.projects.add_members',
        );
    }

    /**
     * Assign an existing client to a project through the documented legacy
     * bulk-project endpoint. The endpoint has no response body, so callers
     * must re-read and verify the project before treating the assignment as
     * confirmed.
     */
    public function assignProjectClient(Project $project, string|int $clientId): void
    {
        $projectId = $this->numericId($project->id, 'projectId');
        $requestedClientId = $this->numericId($clientId, 'clientId');

        if ($project->clientId !== null
            && $this->numericId($project->clientId, 'project.clientId') === $requestedClientId) {
            return;
        }

        $this->transport->send(
            $this->connection,
            new Request(
                operation: 'legacy.projects.assign_client',
                method: 'POST',
                path: "/api/accounts/{$this->accountId()}/projects/bulk",
                legacy: true,
                body: [
                    'projects' => [$projectId],
                    'clientId' => $requestedClientId,
                ],
                retryTransient: false,
            ),
        );
    }

    /** @param list<string|int> $userProfileIds */
    private function updateProjectMembers(
        Project $project,
        array $userProfileIds,
        int $role,
        string $operation,
    ): Project {
        if (! in_array($role, [0, 1], true)) {
            throw new ConfigurationException('TMetric project member role must be 0 or 1.');
        }

        $projectId = $this->numericId($project->id, 'projectId');
        $requestedProfileIds = array_values(array_unique(array_map(
            fn (string|int $profileId): int => $this->numericId($profileId, 'userProfileId'),
            $userProfileIds,
        )));
        $existingProfileIds = array_map(
            fn ($member): int => $this->numericId($member->userProfileId, 'userProfileId'),
            $project->members->all(),
        );
        $missingProfileIds = array_values(array_diff($requestedProfileIds, $existingProfileIds));

        if ($missingProfileIds === []) {
            return $project;
        }

        $payload = $this->normalizeNumericIds($project->raw());
        $members = is_array($payload['members'] ?? null) ? array_values($payload['members']) : [];
        foreach ($missingProfileIds as $profileId) {
            $members[] = [
                'userProfileId' => $profileId,
                'projectId' => $projectId,
                'role' => $role,
            ];
        }
        $payload['members'] = $members;

        $response = $this->transport->send(
            $this->connection,
            new Request(
                operation: $operation,
                method: 'PUT',
                path: "/api/accounts/{$this->accountId()}/projects/{$projectId}",
                legacy: true,
                body: $payload,
                retryTransient: false,
            ),
        );

        return Project::fromArray($response->data === [] ? $payload : $response->data);
    }

    private function normalizeNumericIds(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $childKey => $childValue) {
                $normalized[$childKey] = $this->normalizeNumericIds(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                );
            }

            return $normalized;
        }

        if ($key !== null && ($key === 'id' || str_ends_with($key, 'Id'))) {
            if ($value === null) {
                return null;
            }

            return $this->numericId($value, $key);
        }

        return $value;
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

    private function numericId(string|int $value, string $field): int
    {
        $normalized = (string) $value;

        if (! ctype_digit($normalized) || (int) $normalized <= 0 || (string) (int) $normalized !== ltrim($normalized, '0')) {
            throw new ConfigurationException("TMetric {$field} must be a positive integer ID.");
        }

        return (int) $normalized;
    }
}
