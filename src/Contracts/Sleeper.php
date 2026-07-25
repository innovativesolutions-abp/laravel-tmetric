<?php

namespace InnovativeSolutions\TMetric\Contracts;

interface Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
