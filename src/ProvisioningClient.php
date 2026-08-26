<?php

namespace InnovativeSolutions\TMetric;

use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Data\ProvisionedClient;
use InnovativeSolutions\TMetric\Data\ProvisionedProject;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Exceptions\LegacyApiDisabledException;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;

final readonly class ProvisioningClient
{
    public function __construct(
        private ConnectionConfig $connection,
        private Transport $transport,
    ) {}

    public function createClient(string $name): ProvisionedClient
    {
        if (! $this->connection->legacyEnabled) {
            throw new LegacyApiDisabledException(
                "Legacy TMetric API is disabled for connection [{$this->connection->name}]. Client creation is only documented in API v2.",
            );
        }

        $name = trim($name);
        if ($name === '') {
            throw new ConfigurationException('TMetric client name must not be empty.');
        }

        $response = $this->transport->send(
            $this->connection,
            new Request(
                operation: 'legacy.clients.create',
                method: 'POST',
                path: "/api/accounts/{$this->accountId()}/clients",
                legacy: true,
                body: ['clientName' => $name],
                retryTransient: false,
            ),
        );

        if ($response->data === []) {
            throw new SchemaDriftException('TMetric client create response must contain the created client.');
        }

        return ProvisionedClient::fromArray($response->data);
    }

    public function createProject(string $name, string|int|null $clientId = null): ProvisionedProject
    {
        $name = trim($name);
        if ($name === '') {
            throw new ConfigurationException('TMetric project name must not be empty.');
        }

        $body = ['name' => $name];
        if ($clientId !== null) {
            $body['clientId'] = $this->positiveId($clientId, 'clientId');
        }

        $response = $this->transport->send(
            $this->connection,
            new Request(
                operation: 'projects.create',
                method: 'POST',
                path: "/accounts/{$this->accountId()}/projects",
                body: $body,
                retryTransient: false,
            ),
        );

        if ($response->data === []) {
            throw new SchemaDriftException('TMetric project create response must contain the created project.');
        }

        return ProvisionedProject::fromArray($response->data);
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

    private function positiveId(string|int $value, string $field): int
    {
        $normalized = (string) $value;
        if (! ctype_digit($normalized) || (int) $normalized <= 0) {
            throw new ConfigurationException("TMetric {$field} must be a positive integer ID.");
        }

        return (int) $normalized;
    }
}
