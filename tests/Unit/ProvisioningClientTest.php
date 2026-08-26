<?php

namespace InnovativeSolutions\TMetric\Tests\Unit;

use InnovativeSolutions\TMetric\Connection;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Testing\FakeTransport;
use InnovativeSolutions\TMetric\Tests\TestCase;

class ProvisioningClientTest extends TestCase
{
    public function test_it_creates_a_v3_project_with_client(): void
    {
        $transport = new FakeTransport([[
            'id' => 501,
            'name' => 'Customer Portal',
            'client' => ['id' => 77, 'name' => 'Acme'],
        ]]);
        $connection = new Connection($this->config(), $transport);

        $project = $connection->provisioning()->createProject(' Customer Portal ', 77);

        $this->assertSame('501', $project->id);
        $this->assertSame('Customer Portal', $project->name);
        $this->assertSame('77', $project->clientId);
        $transport->assertRequested(fn (Request $request): bool =>
            $request->operation === 'projects.create'
            && $request->method === 'POST'
            && $request->path === '/accounts/42001/projects'
            && $request->legacy === false
            && $request->retryTransient === false
            && $request->body === ['name' => 'Customer Portal', 'clientId' => 77]
        );
    }

    public function test_it_creates_a_client_through_documented_v2_endpoint(): void
    {
        $transport = new FakeTransport([[
            'clientId' => 88,
            'clientName' => 'New Customer',
        ]]);
        $connection = new Connection($this->config(), $transport);

        $client = $connection->provisioning()->createClient(' New Customer ');

        $this->assertSame('88', $client->id);
        $this->assertSame('New Customer', $client->name);
        $transport->assertRequested(fn (Request $request): bool =>
            $request->operation === 'legacy.clients.create'
            && $request->method === 'POST'
            && $request->path === '/api/accounts/42001/clients'
            && $request->legacy === true
            && $request->retryTransient === false
            && $request->body === ['clientName' => 'New Customer']
        );
    }

    private function config(): ConnectionConfig
    {
        return ConnectionConfig::fromArray('test', [
            'token' => 'secret',
            'account_id' => '42001',
            'legacy_enabled' => true,
            'v3_base_url' => 'https://tmetric.test/api/v3',
            'legacy_base_url' => 'https://tmetric.test',
            'max_attempts' => 1,
        ]);
    }
}
