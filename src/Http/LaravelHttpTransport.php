<?php

namespace InnovativeSolutions\TMetric\Http;

use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\Factory;
use InnovativeSolutions\TMetric\Contracts\Sleeper;
use InnovativeSolutions\TMetric\Contracts\Transport;
use InnovativeSolutions\TMetric\Exceptions\AuthenticationException;
use InnovativeSolutions\TMetric\Exceptions\ForbiddenException;
use InnovativeSolutions\TMetric\Exceptions\MalformedResponseException;
use InnovativeSolutions\TMetric\Exceptions\NotFoundException;
use InnovativeSolutions\TMetric\Exceptions\PartialContentException;
use InnovativeSolutions\TMetric\Exceptions\RateLimitedException;
use InnovativeSolutions\TMetric\Exceptions\TMetricException;
use InnovativeSolutions\TMetric\Exceptions\TransientException;
use InnovativeSolutions\TMetric\Exceptions\TransportException;
use JsonException;
use Throwable;

final class LaravelHttpTransport implements Transport
{
    public function __construct(
        private readonly Factory $http,
        private readonly Sleeper $sleeper,
    ) {}

    public function send(ConnectionConfig $connection, Request $request): Response
    {
        $baseUrl = $request->legacy ? $connection->legacyBaseUrl : $connection->v3BaseUrl;
        $attempt = 0;

        while ($attempt < $connection->maxAttempts) {
            $attempt++;

            try {
                $options = [
                    'allow_redirects' => false,
                    'verify' => true,
                ];

                if ($connection->proxy() !== null) {
                    $options['proxy'] = $connection->proxy()->uri();
                }

                $requestOptions = [
                    'query' => $this->queryString($request->query),
                ];
                if ($request->body !== []) {
                    $requestOptions['json'] = $request->body;
                }

                $response = $this->http
                    ->withToken($connection->token())
                    ->acceptJson()
                    ->timeout($connection->timeout)
                    ->connectTimeout($connection->connectTimeout)
                    ->withOptions($options)
                    ->send($request->method, $baseUrl.$request->path, $requestOptions);
            } catch (LaravelConnectionException) {
                if ($request->retryTransient && $attempt < $connection->maxAttempts) {
                    $this->sleeper->sleepMilliseconds($this->backoffMilliseconds($attempt, null, $connection));

                    continue;
                }

                throw new TransportException(
                    "TMetric transport failed for operation [{$request->operation}] after {$attempt} attempts.",
                    $request->operation,
                    null,
                    $attempt,
                );
            } catch (Throwable) {
                throw new TransportException(
                    "TMetric transport failed for operation [{$request->operation}].",
                    $request->operation,
                    null,
                    $attempt,
                );
            }

            $status = $response->status();
            $retryAfter = $this->retryAfterSeconds($response->header('Retry-After'));

            if ($request->retryTransient
                && $this->isRetryableStatus($status)
                && $attempt < $connection->maxAttempts) {
                $this->sleeper->sleepMilliseconds($this->backoffMilliseconds($attempt, $retryAfter, $connection));

                continue;
            }

            if ($status === 206) {
                throw new PartialContentException(
                    "TMetric returned incomplete partial content for operation [{$request->operation}].",
                    $request->operation,
                    $status,
                    $attempt,
                );
            }

            if ($status < 200 || $status >= 300) {
                throw $this->statusException($request, $status, $attempt, $retryAfter);
            }

            if (trim($response->body()) === '' && $status === 204) {
                return new Response($status, [], $response->headers(), $attempt);
            }

            if (trim($response->body()) === '') {
                throw new MalformedResponseException(
                    "TMetric returned an empty response for operation [{$request->operation}].",
                    $request->operation,
                    $status,
                    $attempt,
                );
            }

            try {
                $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new MalformedResponseException(
                    "TMetric returned malformed JSON for operation [{$request->operation}].",
                    $request->operation,
                    $status,
                    $attempt,
                    $exception,
                );
            }

            if (! is_array($data)) {
                throw new MalformedResponseException(
                    "TMetric returned an unexpected JSON root for operation [{$request->operation}].",
                    $request->operation,
                    $status,
                    $attempt,
                );
            }

            return new Response($status, $data, $response->headers(), $attempt);
        }

        throw new TransportException(
            "TMetric transport failed for operation [{$request->operation}].",
            $request->operation,
            null,
            $attempt,
        );
    }

    /** @param array<string, scalar|array<scalar>|null> $query */
    private function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];

            foreach ($values as $item) {
                $pairs[] = rawurlencode($key).'='.rawurlencode($this->queryValue($item));
            }
        }

        return implode('&', $pairs);
    }

    private function queryValue(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function isRetryableStatus(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    private function backoffMilliseconds(
        int $attempt,
        ?int $retryAfterSeconds,
        ConnectionConfig $connection,
    ): int {
        $cap = $connection->maxRetryDelaySeconds * 1000;

        if ($retryAfterSeconds !== null) {
            return min($cap, max(0, $retryAfterSeconds * 1000));
        }

        $base = min($cap, 250 * (2 ** max(0, $attempt - 1)));
        $jitter = $base > 0 ? random_int(0, min(250, $base)) : 0;

        return min($cap, $base + $jitter);
    }

    private function retryAfterSeconds(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (ctype_digit(trim($value))) {
            return (int) trim($value);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function statusException(
        Request $request,
        int $status,
        int $attempts,
        ?int $retryAfter,
    ): TMetricException {
        $message = "TMetric returned HTTP {$status} for operation [{$request->operation}].";

        return match ($status) {
            401 => new AuthenticationException($message, $request->operation, $status, $attempts),
            403 => new ForbiddenException($message, $request->operation, $status, $attempts),
            404 => new NotFoundException($message, $request->operation, $status, $attempts),
            429 => new RateLimitedException($message, $retryAfter, $request->operation, $status, $attempts),
            408, 500, 502, 503, 504 => new TransientException($message, $request->operation, $status, $attempts),
            default => new TMetricException($message, $request->operation, $status, $attempts),
        };
    }
}
