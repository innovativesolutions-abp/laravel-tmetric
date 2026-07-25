<?php

namespace InnovativeSolutions\TMetric\Http;

use InnovativeSolutions\TMetric\Contracts\Sleeper;

final class NativeSleeper implements Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
