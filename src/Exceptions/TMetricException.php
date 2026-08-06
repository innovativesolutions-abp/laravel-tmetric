<?php

namespace InnovativeSolutions\TMetric\Exceptions;

use RuntimeException;
use Throwable;

class TMetricException extends RuntimeException
{
    /** @var array<string, mixed> */
    public readonly array $safeDetails;

    /** @param array<string, mixed> $safeDetails */
    public function __construct(
        string $message,
        public readonly ?string $operation = null,
        public readonly ?int $status = null,
        public readonly int $attempts = 1,
        ?Throwable $previous = null,
        array $safeDetails = [],
    ) {
        parent::__construct($message, 0, $previous);
        $this->safeDetails = $safeDetails;
    }
}
