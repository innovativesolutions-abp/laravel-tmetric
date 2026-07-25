<?php

namespace InnovativeSolutions\TMetric\Http;

final readonly class Response
{
    /**
     * @param  array<string, list<string>>  $headers
     * @param  array<mixed>  $data
     */
    public function __construct(
        public int $status,
        public array $data,
        public array $headers = [],
        public int $attempts = 1,
    ) {}
}
