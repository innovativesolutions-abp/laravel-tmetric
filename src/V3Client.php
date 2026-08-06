<?php

namespace InnovativeSolutions\TMetric;

use DateTimeInterface;
use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Data\Client;
use InnovativeSolutions\TMetric\Data\DataCollection;
use InnovativeSolutions\TMetric\Data\Task;
use InnovativeSolutions\TMetric\Data\TimeEntry;
use InnovativeSolutions\TMetric\Data\TimeEntryProject;
use InnovativeSolutions\TMetric\Data\TimeTrackingStatus;
use InnovativeSolutions\TMetric\Data\UserBasic;
use InnovativeSolutions\TMetric\Data\UserProfile;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;

final readonly class V3Client
{
    public function __construct(
        private ConnectionConfig $connection,
        private Transport $transport,
    ) {}

    public function user(): UserProfile
    {
        $response = $this->transport->send(
            $this->connection,
            new Request('user.get', 'GET', '/user'),
        );

        return UserProfile::fromArray($response->data);
    }

    /** @return DataCollection<Client> */
    public function clients(): DataCollection
    {
        return $this->collection(
            'clients.list',
            "/accounts/{$this->accountId()}/clients",
            Client::fromArray(...),
        );
    }

    /** @param array<string, scalar|array<scalar>|null> $filters @return DataCollection<Task> */
    public function tasks(array $filters = []): DataCollection
    {
        return $this->collection(
            'tasks.list',
            "/accounts/{$this->accountId()}/tasks",
            Task::fromArray(...),
            $filters,
        );
    }

    /** @return DataCollection<TimeEntryProject> */
    public function timeEntryProjects(): DataCollection
    {
        return $this->collection(
            'time_entry_projects.list',
            "/accounts/{$this->accountId()}/timeentries/projects",
            TimeEntryProject::fromArray(...),
        );
    }

    /** @return DataCollection<TimeEntry> */
    public function timeEntries(string|int $userId, DateTimeInterface $startDate, DateTimeInterface $endDate): DataCollection
    {
        return $this->collection(
            'time_entries.list',
            "/accounts/{$this->accountId()}/timeentries",
            TimeEntry::fromArray(...),
            [
                'userId' => (string) $userId,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
        );
    }

    public function latestTimeEntry(string|int $userId): ?TimeEntry
    {
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'time_entries.latest',
                'GET',
                "/accounts/{$this->accountId()}/timeentries/latest",
                ['userId' => (string) $userId],
            ),
        );

        return $response->data === [] ? null : TimeEntry::fromArray($response->data);
    }

    public function updateTimeEntryProject(TimeEntry $entry, int $projectId): ?TimeEntry
    {
        if ($projectId <= 0 || ! ctype_digit($entry->id) || (int) $entry->id <= 0) {
            throw new ConfigurationException('TMetric time-entry and project IDs must be positive integers.');
        }

        $raw = $entry->raw();
        $task = $raw['task'] ?? null;
        $tags = $raw['tags'] ?? [];
        if (! is_array($task) || ! isset($task['id']) || ! ctype_digit((string) $task['id'])) {
            throw new SchemaDriftException('TMetric time entry must contain a numeric task before its project can change.');
        }
        if (! is_array($tags) || ! array_is_list($tags) || $entry->startTime === null || $entry->endTime === null) {
            throw new SchemaDriftException('TMetric time entry must contain complete time and tag fields before its project can change.');
        }
        $task['id'] = (int) $task['id'];
        foreach ($tags as $index => $tag) {
            if (! is_array($tag) || ! isset($tag['id']) || ! ctype_digit((string) $tag['id'])) {
                throw new SchemaDriftException('TMetric time-entry tags must contain numeric IDs.');
            }
            $tags[$index]['id'] = (int) $tag['id'];
        }
        $body = [
            'project' => ['id' => $projectId],
            'task' => $task,
            'tags' => $tags,
            'startTime' => $entry->startTime,
            'endTime' => $entry->endTime,
        ];
        if ($entry->note !== null) {
            $body['note'] = $entry->note;
        }

        $response = $this->transport->send(
            $this->connection,
            new Request(
                operation: 'time_entries.update_project',
                method: 'PUT',
                path: "/accounts/{$this->accountId()}/timeentries/".rawurlencode($entry->id),
                body: $body,
                retryTransient: false,
            ),
        );

        return $response->data === [] ? null : TimeEntry::fromArray($response->data);
    }

    /** @return DataCollection<TimeTrackingStatus> */
    public function timeTrackingStatuses(string|int|null $teamId = null): DataCollection
    {
        return $this->collection(
            'time_entries.statuses',
            "/accounts/{$this->accountId()}/timeentries/statuses",
            TimeTrackingStatus::fromArray(...),
            ['teamId' => $teamId === null ? null : (string) $teamId],
        );
    }

    /** @return DataCollection<UserBasic> */
    public function reportUsers(): DataCollection
    {
        $response = $this->transport->send(
            $this->connection,
            new Request(
                'reports.project_filter',
                'GET',
                "/accounts/{$this->accountId()}/reports/projects/filter",
            ),
        );

        $users = $response->data['users'] ?? [];

        if (! is_array($users) || ! array_is_list($users)) {
            throw new SchemaDriftException(
                'TMetric report user filter must contain a users list.',
            );
        }

        return DataCollection::fromRows($users, UserBasic::fromArray(...));
    }

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $factory
     * @param  array<string, scalar|array<scalar>|null>  $query
     * @return DataCollection<T>
     */
    private function collection(string $operation, string $path, callable $factory, array $query = []): DataCollection
    {
        $response = $this->transport->send(
            $this->connection,
            new Request($operation, 'GET', $path, $query),
        );

        return DataCollection::fromRows($response->data, $factory);
    }

    private function accountId(): string
    {
        if ($this->connection->accountId === null || $this->connection->accountId === '') {
            throw new ConfigurationException(
                "TMetric connection [{$this->connection->name}] has no account_id.",
            );
        }

        return rawurlencode($this->connection->accountId);
    }
}
