<?php

namespace InnovativeSolutions\TMetric\Exceptions;

class RateLimitedException extends TMetricException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds,
        ?string $operation = null,
        ?int $status = null,
        int $attempts = 1,
        array $safeDetails = [],
    ) {
        parent::__construct($message, $operation, $status, $attempts, safeDetails: $safeDetails);
    }
}
