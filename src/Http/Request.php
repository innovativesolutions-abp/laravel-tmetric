<?php

namespace InnovativeSolutions\TMetric\Http;

final readonly class Request
{
    /** @param array<string, scalar|array<scalar>|null> $query */
    public function __construct(
        public string $operation,
        public string $method,
        public string $path,
        public array $query = [],
        public bool $legacy = false,
    ) {}

    /** @return array<string, mixed> */
    public function safeContext(): array
    {
        return [
            'operation' => $this->operation,
            'method' => $this->method,
            'path' => $this->path,
            'legacy' => $this->legacy,
        ];
    }
}
