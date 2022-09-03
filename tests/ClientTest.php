<?php

namespace Mehdibo\FortyTwo\Client\Test;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Mehdibo\FortyTwo\Client\Client;
use Mehdibo\FortyTwo\Client\Exception\RateLimitReached;
use Mehdibo\FortyTwo\Client\Exception\ServerError;
use Mehdibo\OAuth2\Client\Provider\FortyTwo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ClientTest extends TestCase
{

    /**
     * @return FortyTwo&MockObject
     */
    private function createProvider(): FortyTwo
    {
        $provider = $this->createMock(FortyTwo::class);

        return $provider;
    }

    /**
     * @param array<string, mixed> $expectedOptions
     */
    private function createClient(
        int $expectedRequests = 0,
        string $expectedMethod = "",
        string $expectedUrl = "",
        array $expectedOptions = [],
        ResponseInterface|null $response = null,
        bool $hasExpiredToken = false,
    ): Client {
        if ($response === null) {
            $response = $this->createMock(ResponseInterface::class);
        }

        $accessToken = $this->createMock(AccessTokenInterface::class);
        $accessToken->expects(($hasExpiredToken) ? $this->once() : $this->never())
            ->method('getRefreshToken')
            ->willReturn("refresh_token");
        $accessToken->method('getToken')->willReturn("access_token");
        $accessToken->method('hasExpired')->willReturn($hasExpiredToken);
        $provider = $this->createProvider();
        $provider->method('getAccessToken')->willReturn($accessToken);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly($expectedRequests))
            ->method('request')
            ->with($expectedMethod, $expectedUrl, $expectedOptions)
            ->willReturn($response);
        return new Client(
            $provider,
            $httpClient,
        );
    }

    public function testGetProvider(): void
    {
        $client = $this->createClient();
        $this->assertInstanceOf(FortyTwo::class, $client->getProvider());
    }

    public function testWithProvider(): void
    {
        $provider = new FortyTwo();
        $client = $this->createClient();

        $newClient = $client->withProvider($provider);
        $this->assertNotEquals($client, $newClient);
    }

    public function testFetchTokenFromAuthCode(): void
    {
        $accessToken = $this->createMock(AccessTokenInterface::class);
        $provider = $this->createProvider();
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'some_code'])
            ->willReturn($accessToken);

        $client = new Client($provider);
        $this->assertNull($client->getAccessToken());
        $client->fetchTokenFromAuthCode('some_code');
        $this->assertEquals($accessToken, $client->getAccessToken());
    }

    public function testFetchTokenFromClientCredentials(): void
    {
        $accessToken = $this->createMock(AccessTokenInterface::class);
        $provider = $this->createProvider();
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('client_credentials')
            ->willReturn($accessToken);

        $client = new Client($provider);
        $this->assertNull($client->getAccessToken());
        $client->fetchTokenFromClientCredentials();
        $this->assertEquals($accessToken, $client->getAccessToken());
    }

    /**
     * @dataProvider successfulGetDataProvider
     */
    public function testSuccessfulGet(
        string $expectedUrl,
        string $uri,
        array $query,
        bool $hasExpiredToken,
    ): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
        ];
        $client = $this->createClient(
            1,
            "GET",
            $expectedUrl,
            $expectedOptions,
            $response,
            $hasExpiredToken,
        );

        $client->get($uri, $query);
    }

    /**
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedGet(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void
    {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
        ];
        $client = $this->createClient(
            1,
            "GET",
            $expectedUrl,
            $expectedOptions,
            $response,
        );

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $client->get($uri, []);
    }

    /**
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedPost(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void
    {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
            "json" => [],
        ];
        $client = $this->createClient(
            1,
            "POST",
            $expectedUrl,
            $expectedOptions,
            $response,
        );

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $client->post($uri, []);
    }

    /**
     * @dataProvider successfulPostDataProvider
     */
    public function testSuccessfulPost(
        string $expectedUrl,
        string $uri,
        array $payload,
        bool $hasExpiredToken,
    ): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
            "json" => $payload,
        ];
        $client = $this->createClient(
            1,
            "POST",
            $expectedUrl,
            $expectedOptions,
            $response,
            $hasExpiredToken,
        );

        $client->post($uri, $payload);
    }

    /**
     * @dataProvider successfulPatchDataProvider
     */
    public function testSuccessfulPatch(
        string $expectedUrl,
        string $uri,
        array $payload,
        bool $hasExpiredToken,
    ): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
            "json" => $payload,
        ];
        $client = $this->createClient(
            1,
            "PATCH",
            $expectedUrl,
            $expectedOptions,
            $response,
            $hasExpiredToken,
        );

        $client->patch($uri, $payload);
    }

    /**
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedPatch(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void
    {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
            "json" => [],
        ];
        $client = $this->createClient(
            1,
            "PATCH",
            $expectedUrl,
            $expectedOptions,
            $response,
        );

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $client->patch($uri, []);
    }

    /**
     * @dataProvider successfulDeleteDataProvider
     */
    public function testSuccessfulDelete(
        string $expectedUrl,
        string $uri,
        bool $hasExpiredToken,
    ): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
        ];
        $client = $this->createClient(
            1,
            "DELETE",
            $expectedUrl,
            $expectedOptions,
            $response,
            $hasExpiredToken,
        );

        $client->delete($uri);
    }

    /**
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedDelete(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void
    {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => 'Mehdibo-FT-Client/'.Client::VERSION,
            ],
        ];
        $client = $this->createClient(
            1,
            "DELETE",
            $expectedUrl,
            $expectedOptions,
            $response,
        );

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $client->delete($uri);
    }

    /**
     * @return array<string, mixed>
     */
    public function successfulGetDataProvider(): array
    {
        return [
            "expired token" => [
                'https://api.intra.42.fr/v2/some_endpoint',
                'some_endpoint',
                [],
                true,
            ],
            "no queries" => [
                'https://api.intra.42.fr/v2/some_endpoint',
                'some_endpoint',
                [],
                false,
            ],
            "basic query" => [
                'https://api.intra.42.fr/v2/some_endpoint?key=val',
                'some_endpoint',
                ['key' => 'val'],
                false,
            ],
            "query with special characters" => [
                'https://api.intra.42.fr/v2/some_endpoint?key=val&something%5Bhere%5D=some+value&something'
                . '%5Bthere%5D=some%2Fother',
                'some_endpoint',
                [
                    'key' => 'val',
                    "something[here]" => "some value",
                    "something[there]" => "some/other",
                ],
                false,
            ],
            "query with nested array" => [
                'https://api.intra.42.fr/v2/some_endpoint?filter%5Bfield%5D=1%2C2%2C3&sort%5B0%5D=data',
                'some_endpoint',
                [
                    'filter' => [
                        "field" => "1,2,3",
                    ],
                    'sort' => ["data"],
                ],
                false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function failedRequestDataProvider(): array
    {
        $respRateLimit = $this->createMock(ResponseInterface::class);
        $respRateLimit->method('getStatusCode')->willReturn(429);
        $respRateLimit->method('getHeaders')->willReturn([
            "retry-after" => [1],
        ]);

        $serverErrorResp = $this->createMock(ResponseInterface::class);
        $serverErrorResp->method('getStatusCode')->willReturn(500);


        return [
            "rate limit reached" => [
                'https://api.intra.42.fr/v2/some_endpoint',
                'some_endpoint',
                $respRateLimit,
                RateLimitReached::class,
                "Rate limit reached",
            ],
            "internal server error" => [
                'https://api.intra.42.fr/v2/some_endpoint',
                'some_endpoint',
                $serverErrorResp,
                ServerError::class,
                "Intranet API returned a 500 Internal Server Error",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function successfulPostDataProvider(): array
    {
        return [
            "empty payload" => [
                'https://api.intra.42.fr/v2/user/here/path',
                "/user/here/path",
                [],
                false,
            ],
            "payload" => [
                'https://api.intra.42.fr/v2/user/here/path',
                "/user/here/path",
                [
                    "user" => [
                        "id" => 1,
                        "username" => "hello",
                    ],
                ],
                false,
            ],
            "expired token" => [
                'https://api.intra.42.fr/v2/user/here/path',
                "/user/here/path",
                [
                    "user" => [
                        "id" => 1,
                        "username" => "hello",
                    ],
                ],
                true,
            ],
        ];
    }

    public function successfulPatchDataProvider(): array
    {
        return $this->successfulPostDataProvider();
    }

    public function successfulDeleteDataProvider(): array
    {
        return [
            "basic request" => [
                'https://api.intra.42.fr/v2/user/here/path',
                "/user/here/path",
                false,
            ],
            "expired token" => [
                'https://api.intra.42.fr/v2/user/here/path',
                "/user/here/path",
                true,
            ],
        ];
    }
}
