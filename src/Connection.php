<?php

namespace InnovativeSolutions\TMetric;

use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Exceptions\LegacyApiDisabledException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Legacy\LegacyV2Client;

final readonly class Connection
{
    public function __construct(
        private ConnectionConfig $config,
        private Transport $transport,
    ) {}

    public function v3(): V3Client
    {
        return new V3Client($this->config, $this->transport);
    }

    public function provisioning(): ProvisioningClient
    {
        return new ProvisioningClient($this->config, $this->transport);
    }

    public function legacy(): LegacyV2Client
    {
        if (! $this->config->legacyEnabled) {
            throw new LegacyApiDisabledException(
                "Legacy TMetric API is disabled for connection [{$this->config->name}].",
            );
        }

        return new LegacyV2Client($this->config, $this->transport);
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('TMetric connections cannot be serialized.');
    }
}
