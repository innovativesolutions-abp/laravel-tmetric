<?php

namespace InnovativeSolutions\TMetric\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use InnovativeSolutions\TMetric\Exceptions\AuthenticationException;
use InnovativeSolutions\TMetric\Exceptions\ForbiddenException;
use InnovativeSolutions\TMetric\Exceptions\MalformedResponseException;
use InnovativeSolutions\TMetric\Exceptions\NotFoundException;
use InnovativeSolutions\TMetric\Exceptions\PartialContentException;
use InnovativeSolutions\TMetric\Exceptions\RateLimitedException;
use InnovativeSolutions\TMetric\Exceptions\TransientException;
use InnovativeSolutions\TMetric\Exceptions\TransportException;
use InnovativeSolutions\TMetric\Http\ConnectionConfig;
use InnovativeSolutions\TMetric\Http\LaravelHttpTransport;
use InnovativeSolutions\TMetric\Http\Request;
use InnovativeSolutions\TMetric\Tests\Support\RecordingSleeper;
use InnovativeSolutions\TMetric\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LaravelHttpTransportTest extends TestCase
{
    public function test_it_sends_bearer_auth_without_exposing_it_in_safe_context(): void
    {
        $options = $this->recordRequestOptions();
        Http::fake(['tmetric.test/*' => Http::response(['id' => 101], 200)]);

        $transport = new LaravelHttpTransport(app(Factory::class), new RecordingSleeper);
        $request = new Request('user.get', 'GET', '/user');
        $response = $transport->send($this->connection(), $request);

        self::assertSame(101, $response->data['id']);
        self::assertArrayNotHasKey('headers', $request->safeContext());
        self::assertStringNotContainsString('synthetic-secret-token', serialize($request->safeContext()));
        self::assertSame('socks5h://tmetric-egress.test:1080', $options[0]['proxy']);
        self::assertFalse($options[0]['allow_redirects']);
        self::assertTrue($options[0]['verify']);

        Http::assertSent(fn ($sent): bool => $sent->hasHeader('Authorization', 'Bearer synthetic-secret-token'));
    }

    public function test_it_serializes_array_query_values_as_repeated_openapi_form_parameters(): void
    {
        Http::fake(['tmetric.test/*' => Http::response([], 200)]);

        $this->transport()->send(
            $this->connection(),
            new Request('legacy.test', 'GET', '/api/test', [
                'ProfileList' => ['101', '102'],
                'UseUtcTime' => true,
                'Ignored' => null,
            ], true),
        );

        Http::assertSent(
            fn ($sent): bool => str_contains($sent->url(), 'ProfileList=101&ProfileList=102')
                && str_contains($sent->url(), 'UseUtcTime=true')
                && ! str_contains($sent->url(), 'Ignored'),
        );
    }

    public function test_it_sends_a_json_body_without_exposing_raw_values_in_safe_context(): void
    {
        Http::fake(['tmetric.test/*' => Http::response(['id' => 7001], 200)]);
        $request = new Request(
            operation: 'time_entries.update_project',
            method: 'PUT',
            path: '/accounts/42001/timeentries/7001',
            body: ['project' => ['id' => '9002']],
            retryTransient: false,
        );

        $this->transport()->send($this->connection(), $request);

        Http::assertSent(fn ($sent): bool => $sent->method() === 'PUT'
            && $sent->data() === ['project' => ['id' => '9002']]);
        self::assertArrayHasKey('body_hash', $request->safeContext());
        self::assertArrayNotHasKey('body', $request->safeContext());
        self::assertStringNotContainsString('9002', serialize($request->safeContext()));
    }

    public function test_it_accepts_an_empty_success_response_for_a_write(): void
    {
        Http::fake(['tmetric.test/*' => Http::response('', 204)]);

        $response = $this->transport()->send(
            $this->connection(),
            new Request(
                operation: 'time_entries.update_project',
                method: 'PUT',
                path: '/accounts/42001/timeentries/7001',
                body: ['project' => ['id' => '9002']],
            ),
        );

        self::assertSame(204, $response->status);
        self::assertSame([], $response->data);
    }

    public function test_it_rejects_an_empty_200_response_for_a_write(): void
    {
        Http::fake(['tmetric.test/*' => Http::response('', 200)]);

        $this->expectException(MalformedResponseException::class);

        $this->transport()->send(
            $this->connection(),
            new Request(
                operation: 'time_entries.update_project',
                method: 'PUT',
                path: '/accounts/42001/timeentries/7001',
                body: ['project' => ['id' => '9002']],
            ),
        );
    }

    public function test_a_custom_ambiguous_write_is_never_retried_by_default(): void
    {
        Http::fake(['tmetric.test/*' => Http::response(['error' => 'temporary'], 503)]);

        try {
            $this->transport()->send(
                $this->connection(),
                new Request(
                    operation: 'time_entries.update_project',
                    method: 'PUT',
                    path: '/accounts/42001/timeentries/7001',
                    body: ['project' => ['id' => '9002']],
                    retryTransient: true,
                ),
            );
            self::fail('Expected transient exception.');
        } catch (TransientException $exception) {
            self::assertSame(1, $exception->attempts);
        }

        Http::assertSentCount(1);
    }

    public function test_a_write_connection_failure_is_never_retried_by_default(): void
    {
        Http::fake(['tmetric.test/*' => Http::failedConnection('synthetic connection failure')]);

        try {
            $this->transport()->send(
                $this->connection(),
                new Request(
                    operation: 'time_entries.update_project',
                    method: 'PUT',
                    path: '/accounts/42001/timeentries/7001',
                    body: ['project' => ['id' => '9002']],
                ),
            );
            self::fail('Expected transport exception.');
        } catch (TransportException $exception) {
            self::assertSame(1, $exception->attempts);
        }

        Http::assertSentCount(1);
    }

    public function test_a_blank_200_read_response_is_rejected(): void
    {
        Http::fake(['tmetric.test/*' => Http::response('', 200)]);

        $this->expectException(MalformedResponseException::class);

        $this->transport()->send($this->connection(), new Request('user.get', 'GET', '/user'));
    }

    public function test_generic_connection_without_proxy_omits_the_proxy_option_but_keeps_tls_verification(): void
    {
        $options = $this->recordRequestOptions();
        Http::fake(['tmetric.test/*' => Http::response(['id' => 101], 200)]);
        $config = config('tmetric.connections.default');
        unset($config['proxy']);

        $this->transport()->send(
            ConnectionConfig::fromArray('default', $config),
            new Request('user.get', 'GET', '/user'),
        );

        self::assertArrayNotHasKey('proxy', $options[0]);
        self::assertTrue($options[0]['verify']);
        self::assertFalse($options[0]['allow_redirects']);
    }

    public function test_it_passes_a_generic_http_connect_proxy_to_guzzle_with_tls_verification(): void
    {
        $options = $this->recordRequestOptions();
        Http::fake(['tmetric.test/*' => Http::response(['id' => 101], 200)]);
        $config = config('tmetric.connections.default');
        $config['proxy'] = 'http://private-proxy.test:8890';

        $this->transport()->send(
            ConnectionConfig::fromArray('default', $config),
            new Request('user.get', 'GET', '/user'),
        );

        self::assertSame('http://private-proxy.test:8890', $options[0]['proxy']);
        self::assertTrue($options[0]['verify']);
        self::assertFalse($options[0]['allow_redirects']);
    }

    public function test_it_does_not_retry_authentication_failures_or_leak_response_content(): void
    {
        Http::fake(['tmetric.test/*' => Http::response([
            'message' => 'Authorization Bearer synthetic-secret-token',
        ], 401)]);

        try {
            $this->transport()->send($this->connection(), new Request('user.get', 'GET', '/user'));
            self::fail('Expected authentication exception.');
        } catch (AuthenticationException $exception) {
            self::assertSame(1, $exception->attempts);
            self::assertStringNotContainsString('synthetic-secret-token', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_it_exposes_bounded_sanitized_negative_response_details(): void
    {
        Http::fake(['tmetric.test/*' => Http::response([
            'statusCode' => 403,
            'message' => 'A task link is required.',
            'token' => 'must-not-leak',
            'errors' => [['field' => 'task', 'message' => 'Required']],
        ], 403)]);

        try {
            $this->transport()->send($this->connection(), new Request('time_entries.update_project', 'PUT', '/test'));
            self::fail('Expected forbidden exception.');
        } catch (ForbiddenException $exception) {
            self::assertSame(403, $exception->status);
            self::assertSame('A task link is required.', $exception->safeDetails['response']['message']);
            self::assertSame('[redacted]', $exception->safeDetails['response']['token']);
            self::assertSame('Required', $exception->safeDetails['response']['errors'][0]['message']);
            self::assertArrayHasKey('body_sha256', $exception->safeDetails);
            self::assertStringNotContainsString('must-not-leak', serialize($exception->safeDetails));
        }
    }

    public function test_it_retries_429_with_retry_after_and_then_reports_exhaustion(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'rate limited'], 429, ['Retry-After' => '2'])
            ->push(['error' => 'rate limited'], 429, ['Retry-After' => '2'])
            ->push(['error' => 'rate limited'], 429, ['Retry-After' => '2']);

        $sleeper = new RecordingSleeper;
        $transport = new LaravelHttpTransport(app(Factory::class), $sleeper);

        try {
            $transport->send($this->connection(), new Request('user.get', 'GET', '/user'));
            self::fail('Expected rate limit exception.');
        } catch (RateLimitedException $exception) {
            self::assertSame(2, $exception->retryAfterSeconds);
            self::assertSame(3, $exception->attempts);
        }

        self::assertSame([2000, 2000], $sleeper->milliseconds);
        Http::assertSentCount(3);
    }

    public function test_it_rejects_malformed_json(): void
    {
        Http::fake(['tmetric.test/*' => Http::response('<html>not-json</html>', 200)]);

        $this->expectException(MalformedResponseException::class);

        $this->transport()->send($this->connection(), new Request('user.get', 'GET', '/user'));
    }

    public static function nonRetryableStatusProvider(): array
    {
        return [
            'forbidden' => [403, ForbiddenException::class],
            'not found' => [404, NotFoundException::class],
        ];
    }

    #[DataProvider('nonRetryableStatusProvider')]
    public function test_it_does_not_retry_non_retryable_statuses(int $status, string $exceptionClass): void
    {
        Http::fake(['tmetric.test/*' => Http::response(['error' => 'synthetic'], $status)]);

        try {
            $this->transport()->send($this->connection(), new Request('test.operation', 'GET', '/test'));
            self::fail('Expected typed exception.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame(1, $exception->attempts);
        }

        Http::assertSentCount(1);
    }

    public function test_it_retries_transient_server_errors_and_can_recover(): void
    {
        $options = $this->recordRequestOptions();
        Http::fakeSequence()
            ->push(['error' => 'temporary'], 503)
            ->push(['id' => 101], 200);

        $sleeper = new RecordingSleeper;
        $response = (new LaravelHttpTransport(app(Factory::class), $sleeper))
            ->send($this->connection(), new Request('user.get', 'GET', '/user'));

        self::assertSame(2, $response->attempts);
        self::assertSame(101, $response->data['id']);
        self::assertCount(1, $sleeper->milliseconds);
        self::assertGreaterThanOrEqual(0, $sleeper->milliseconds[0]);
        Http::assertSentCount(2);
        self::assertSame([
            'socks5h://tmetric-egress.test:1080',
            'socks5h://tmetric-egress.test:1080',
        ], array_column($options->getArrayCopy(), 'proxy'));
        self::assertSame([false, false], array_column($options->getArrayCopy(), 'allow_redirects'));
        self::assertSame([true, true], array_column($options->getArrayCopy(), 'verify'));
    }

    public function test_it_reports_exhausted_connection_failures_without_raw_transport_text(): void
    {
        $options = $this->recordRequestOptions();
        Http::fake([
            'tmetric.test/*' => Http::failedConnection(
                'Bearer synthetic-secret-token via socks5h://proxy-secret.test:1080',
            ),
        ]);

        try {
            $this->transport()->send($this->connection(), new Request('user.get', 'GET', '/user'));
            self::fail('Expected transport exception.');
        } catch (TransportException $exception) {
            self::assertSame(3, $exception->attempts);
            self::assertStringNotContainsString('synthetic-secret-token', $exception->getMessage());
            self::assertStringNotContainsString('synthetic-secret-token', (string) $exception);
            self::assertStringNotContainsString('proxy-secret.test', $exception->getMessage());
            self::assertStringNotContainsString('proxy-secret.test', (string) $exception);
            self::assertNull($exception->getPrevious());
        }

        Http::assertSentCount(3);
        self::assertSame([
            'socks5h://tmetric-egress.test:1080',
            'socks5h://tmetric-egress.test:1080',
            'socks5h://tmetric-egress.test:1080',
        ], array_column($options->getArrayCopy(), 'proxy'));
        self::assertSame([false, false, false], array_column($options->getArrayCopy(), 'allow_redirects'));
        self::assertSame([true, true, true], array_column($options->getArrayCopy(), 'verify'));
    }

    public function test_exhausted_server_failure_is_typed(): void
    {
        Http::fake(['tmetric.test/*' => Http::response(['error' => 'temporary'], 503)]);

        $this->expectException(TransientException::class);

        $this->transport()->send($this->connection(), new Request('user.get', 'GET', '/user'));
    }

    public function test_partial_content_fails_explicitly_instead_of_silently_dropping_tasks(): void
    {
        Http::fake(['tmetric.test/*' => Http::response([['id' => 1]], 206)]);

        try {
            $this->transport()->send($this->connection(), new Request('tasks.list', 'GET', '/tasks'));
            self::fail('Expected partial content exception.');
        } catch (PartialContentException $exception) {
            self::assertSame(206, $exception->status);
            self::assertSame('tasks.list', $exception->operation);
        }

        Http::assertSentCount(1);
    }

    private function transport(): LaravelHttpTransport
    {
        return new LaravelHttpTransport(app(Factory::class), new RecordingSleeper);
    }

    private function connection(): ConnectionConfig
    {
        return ConnectionConfig::fromArray('default', config('tmetric.connections.default'));
    }

    /** @return \ArrayObject<int, array<string, mixed>> */
    private function recordRequestOptions(): \ArrayObject
    {
        $options = new \ArrayObject;

        app(Factory::class)->globalMiddleware(
            static fn (callable $handler): callable => static function ($request, array $requestOptions) use (
                $handler,
                $options,
            ) {
                $options->append($requestOptions);

                return $handler($request, $requestOptions);
            },
        );

        return $options;
    }
}
