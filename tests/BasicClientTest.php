<?php

namespace Mehdibo\FortyTwo\Client\Test;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Mehdibo\FortyTwo\Client\BasicClient;
use Mehdibo\FortyTwo\Client\Exception\EnumerationRateLimited;
use Mehdibo\FortyTwo\Client\Exception\RateLimitReached;
use Mehdibo\FortyTwo\Client\Exception\ServerError;
use Mehdibo\OAuth2\Client\Provider\FortyTwo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BasicClientTest extends TestCase
{

    private const USER_AGENT = "Mehdibo-FT-Client/".BasicClient::VERSION;

    /**
     * @return FortyTwo&MockObject
     */
    private function createProvider(AccessTokenInterface $accessToken): FortyTwo
    {
        $provider = $this->createMock(FortyTwo::class);
        $provider->method('getAccessToken')->willReturn($accessToken);
        return $provider;
    }

    /**
     * @return AccessTokenInterface&MockObject
     */
    private function createAccessToken(bool $hasExpiredToken = false): AccessTokenInterface
    {
        $accessToken = $this->createMock(AccessTokenInterface::class);
        $accessToken->expects(($hasExpiredToken) ? $this->once() : $this->never())
            ->method('getRefreshToken')
            ->willReturn("refresh_token");
        $accessToken->method('getToken')->willReturn("access_token");
        $accessToken->method('hasExpired')->willReturn($hasExpiredToken);
        return $accessToken;
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
    ): BasicClient {
        if ($response === null) {
            $response = $this->createMock(ResponseInterface::class);
        }

        $accessToken = $this->createAccessToken($hasExpiredToken);
        $provider = $this->createProvider($accessToken);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly($expectedRequests))
            ->method('request')
            ->with($expectedMethod, $expectedUrl, $expectedOptions)
            ->willReturn($response);
        return new BasicClient(
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
        $provider = $this->createProvider($accessToken);
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'some_code'])
            ->willReturn($accessToken);

        $client = new BasicClient($provider);
        $this->assertNull($client->getAccessToken());
        $client->fetchTokenFromAuthCode('some_code');
        $this->assertEquals($accessToken, $client->getAccessToken());
    }

    public function testFetchTokenFromClientCredentials(): void
    {
        $accessToken = $this->createMock(AccessTokenInterface::class);
        $provider = $this->createProvider($accessToken);
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('client_credentials')
            ->willReturn($accessToken);

        $client = new BasicClient($provider);
        $this->assertNull($client->getAccessToken());
        $client->fetchTokenFromClientCredentials();
        $this->assertEquals($accessToken, $client->getAccessToken());
    }

    /**
     * @param array<string, string> $query
     * @dataProvider successfulGetDataProvider
     */
    public function testSuccessfulGet(
        string $expectedUrl,
        string $uri,
        array $query,
        bool $hasExpiredToken,
    ): void {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param class-string<\Throwable> $expectedException
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedGet(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param class-string<\Throwable> $expectedException
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedPost(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param array<string, mixed> $payload
     * @dataProvider successfulPostDataProvider
     */
    public function testSuccessfulPost(
        string $expectedUrl,
        string $uri,
        array $payload,
        bool $hasExpiredToken,
    ): void {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param array<string, mixed> $payload
     * @dataProvider successfulPatchDataProvider
     */
    public function testSuccessfulPatch(
        string $expectedUrl,
        string $uri,
        array $payload,
        bool $hasExpiredToken,
    ): void {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param class-string<\Throwable> $expectedException
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedPatch(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
    ): void {
        $response = $this->createMock(ResponseInterface::class);
        $response->method("getStatusCode")->willReturn(200);

        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @param class-string<\Throwable> $expectedException
     * @dataProvider failedRequestDataProvider
     */
    public function testFailedDelete(
        string $expectedUrl,
        string $uri,
        ResponseInterface $response,
        string $expectedException,
        string $expectedExceptionMessage,
    ): void {
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
                'User-Agent' => self::USER_AGENT,
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
     * @dataProvider enumerateDataProvider
     */
    public function testEnumerate(
        int $maxItems,
        int $startPage,
        int $expectedItemsCount,
        int $expectedPagesCount,
    ): void {
        $provider = $this->createProvider($this->createAccessToken());

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly($expectedPagesCount))
            ->method('request')
            ->willReturnCallback(
                function (string $method, string $endpoint, array $options) use ($maxItems, $startPage) {
                    static $pageI = 0;

                // Maximum items per page cannot exceed 100
                    $perPage = ($maxItems !== 0 && $maxItems < 100) ? $maxItems : 100;

                // We calculate the current page based on the start page and how many requests have been made
                    $startPage = ($startPage <= 0) ? 1 : $startPage;
                    $currentPage = $startPage + $pageI;

                    $this->assertEquals("GET", $method);
                    $this->assertEquals(
                        "https://api.intra.42.fr/v2/some_endpoint?filter%5Bfield%5D=value"
                        ."&page=".$currentPage."&per_page=".$perPage,
                        $endpoint,
                    );
                    $this->assertEquals([
                    'headers' => [
                        'Authorization' => 'Bearer access_token',
                        'User-Agent' => self::USER_AGENT,
                    ],
                    ], $options);

                // Response is always 3 pages and the last page only contains half the items
                /**
                 * @var array<int<0, max>> $pages
                 */
                    $pages = [
                    $perPage,
                    $perPage,
                    (int) ($perPage / 2),
                    ];

                // We create a response based on the Page number
                    $resp = $this->createMock(ResponseInterface::class);
                    $resp->method('getStatusCode')->willReturn(200);
                    $resp->method('toArray')
                    ->willReturn(
                        array_fill(0, $pages[$startPage + $pageI - 1], [
                            "id" => 1,
                            "username" => "someone",
                        ]),
                    );
                    $pageI++;
                    return $resp;
                },
            );

        $client = new BasicClient($provider, $httpClient);
        $generator = $client->enumerate("/some_endpoint", ["filter[field]" => "value"], $maxItems, $startPage);
        $this->assertIsIterable($generator);
        $itemCount = 0;
        foreach ($generator as $item) {
            $this->assertEquals(1, $item["id"]);
            $this->assertEquals("someone", $item["username"]);
            $itemCount++;
        }
        $this->assertEquals($expectedItemsCount, $itemCount);
    }

    public function testEnumerateReachesRateLimit(): void
    {
        $provider = $this->createProvider($this->createAccessToken());

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnCallback(
                function (string $method, string $endpoint, array $options) {
                    static $pageI = 0;

                    $startPage = 1;
                    // Maximum items per page cannot exceed 100
                    $perPage = 100;

                    // We calculate the current page based on the start page and how many requests have been made
                    $currentPage = $startPage + $pageI;

                    $this->assertEquals("GET", $method);
                    $this->assertEquals(
                        "https://api.intra.42.fr/v2/some_endpoint?filter%5Bfield%5D=value"
                        ."&page=".$currentPage."&per_page=".$perPage,
                        $endpoint,
                    );
                    $this->assertEquals([
                        'headers' => [
                            'Authorization' => 'Bearer access_token',
                            'User-Agent' => self::USER_AGENT,
                        ],
                    ], $options);

                    // Response is always 3 pages and the last page only contains half the items
                    $pages = [
                        $perPage,
                        $perPage,
                    ];

                    $resp = $this->createMock(ResponseInterface::class);

                    if ($pageI === 2) {
                        $resp->method('getStatusCode')->willReturn(429);
                        $resp->method('getHeaders')->willReturn([
                            'retry-after' => [42],
                        ]);
                        return $resp;
                    }

                    // We create a response based on the Page number
                    $resp->method('getStatusCode')->willReturn(200);
                    $resp->method('toArray')
                        ->willReturn(
                            array_fill(0, $pages[$startPage + $pageI - 1], [
                                "id" => 1,
                                "username" => "someone",
                            ]),
                        );
                    $pageI++;
                    return $resp;
                },
            );

        $client = new BasicClient($provider, $httpClient);
        $generator = $client->enumerate("/some_endpoint", ["filter[field]" => "value"]);
        $this->assertIsIterable($generator);
        $itemCount = 0;
        try {
            foreach ($generator as $item) {
                $this->assertEquals(1, $item["id"]);
                $this->assertEquals("someone", $item["username"]);
                $itemCount++;
            }
        } catch (\Exception $e) {
            $this->assertInstanceOf(EnumerationRateLimited::class, $e);
            /**
             * @var EnumerationRateLimited $e
             */
            $this->assertEquals(42, $e->retryAfter);
            $this->assertEquals(3, $e->reachedPage);
        }
        $this->assertEquals(200, $itemCount);
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

    /**
     * @return array<string, mixed>
     */
    public function successfulPatchDataProvider(): array
    {
        return $this->successfulPostDataProvider();
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, int[]>
     */
    public function enumerateDataProvider(): array
    {
        return [
            "no max item and start from negative page" => [
                0,
                -1,
                250,
                3,
            ],
            "no max item and start from page zero" => [
                0,
                0,
                250,
                3,
            ],
            "no max item and start from first page" => [
                0,
                1,
                250,
                3,
            ],
            "no max item and start from second page" => [
                0,
                2,
                150,
                2,
            ],
            "no max item and start from last page" => [
                0,
                3,
                50,
                1,
            ],
            "max item and start from first page" => [
                200,
                1,
                200,
                2,
            ],
            "max item and start from second page" => [
                200,
                2,
                150,
                2,
            ],
            "max item and start from last page" => [
                200,
                3,
                50,
                1,
            ],
        ];
    }
}
