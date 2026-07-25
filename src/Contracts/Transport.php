<?php

namespace InnovativeSolutions\TMetric\Contracts;

use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Http\Response;

interface Transport
{
    public function send(ConnectionConfig $connection, Request $request): Response;
}
