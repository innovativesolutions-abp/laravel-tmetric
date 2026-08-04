<?php

namespace InnovativeSolutions\TMetric\Http;

final readonly class Request
{
    public bool $retryTransient;

    /**
     * @param  array<string, scalar|array<scalar>|null>  $query
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public string $operation,
        public string $method,
        public string $path,
        public array $query = [],
        public bool $legacy = false,
        public array $body = [],
        ?bool $retryTransient = null,
    ) {
        $safeReadMethod = in_array(strtoupper($this->method), ['GET', 'HEAD', 'OPTIONS'], true);
        $this->retryTransient = $safeReadMethod && ($retryTransient ?? true);
    }

    /** @return array<string, mixed> */
    public function safeContext(): array
    {
        $context = [
            'operation' => $this->operation,
            'method' => $this->method,
            'path' => $this->path,
            'legacy' => $this->legacy,
            'retry_transient' => $this->retryTransient,
        ];

        if ($this->body !== []) {
            $context['body_hash'] = hash(
                'sha256',
                json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        return $context;
    }
}
