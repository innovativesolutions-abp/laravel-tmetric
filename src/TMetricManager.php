<?php

namespace InnovativeSolutions\TMetric;

use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Exceptions\ConfigurationException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;

final readonly class TMetricManager
{
    /** @param array<string, mixed> $config */
    public function __construct(
        #[\SensitiveParameter]
        private array $config,
        private Transport $transport,
    ) {}

    public function connection(?string $name = null): Connection
    {
        $name ??= (string) ($this->config['default'] ?? 'default');
        $connections = $this->config['connections'] ?? [];

        if (! is_array($connections) || ! is_array($connections[$name] ?? null)) {
            throw new ConfigurationException("TMetric connection [{$name}] is not configured.");
        }

        return new Connection(
            ConnectionConfig::fromArray($name, $connections[$name]),
            $this->transport,
        );
    }

    /** @param array<string, mixed> $config */
    public function connect(#[\SensitiveParameter] array $config, string $name = 'runtime'): Connection
    {
        return new Connection(
            ConnectionConfig::fromArray($name, $config),
            $this->transport,
        );
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('TMetric manager cannot be serialized.');
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return [
            'config' => '[REDACTED]',
            'transport' => get_debug_type($this->transport),
        ];
    }
}
