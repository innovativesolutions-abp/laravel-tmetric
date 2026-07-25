<?php

namespace InnovativeSolutions\TMetric\Testing;

use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Exceptions\UnexpectedRequestException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Http\Response;
use Throwable;

final class FakeTransport implements Transport
{
    /** @var list<Response|array<mixed>|Throwable|callable(Request): (Response|array<mixed>)> */
    private array $responses;

    /** @var list<Request> */
    private array $requests = [];

    /** @param list<Response|array<mixed>|Throwable|callable(Request): (Response|array<mixed>)> $responses */
    public function __construct(array $responses = [])
    {
        $this->responses = array_values($responses);
    }

    public function send(ConnectionConfig $connection, Request $request): Response
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new UnexpectedRequestException(
                "No fake TMetric response was registered for operation [{$request->operation}].",
                $request->operation,
            );
        }

        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        if (is_callable($response)) {
            $response = $response($request);
        }

        return $response instanceof Response ? $response : new Response(200, $response);
    }

    /** @return list<Request> */
    public function recorded(): array
    {
        return $this->requests;
    }

    public function assertRequested(callable $predicate): void
    {
        foreach ($this->requests as $request) {
            if ($predicate($request) === true) {
                return;
            }
        }

        throw new \RuntimeException('The expected TMetric request was not recorded.');
    }

    public function assertRequestCount(int $expected): void
    {
        if (count($this->requests) !== $expected) {
            throw new \RuntimeException(
                "Expected {$expected} TMetric requests, recorded ".count($this->requests).'.',
            );
        }
    }
}
