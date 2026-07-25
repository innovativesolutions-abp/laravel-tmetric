<?php

namespace InnovativeSolutions\TMetric\Tests\Support;

use InnovativeSolutions\TMetric\Contracts\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $milliseconds = [];

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->milliseconds[] = $milliseconds;
    }
}
