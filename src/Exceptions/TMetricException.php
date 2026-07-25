<?php

namespace InnovativeSolutions\TMetric\Exceptions;

use RuntimeException;
use Throwable;

class TMetricException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $operation = null,
        public readonly ?int $status = null,
        public readonly int $attempts = 1,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
