<?php

namespace Mehdibo\FortyTwo\SDK\Test;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Mehdibo\FortyTwo\SDK\Client;
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
    ): Client
    {
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

    public function testSetProvider(): void
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

    public function testGet(): void
    {
        $expectedRequests = 1;
        $expectedMethod = "GET";
        $expectedUrl = 'https://api.intra.42.fr/v2/some_endpoint?filter%5Bfield%5D=value';
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer access_token',
            ],
        ];
        $client = $this->createClient(
            $expectedRequests,
            $expectedMethod,
            $expectedUrl,
            $expectedOptions,
        );

        $client->get("/some_endpoint", ["filter[field]" => "value"]);

        $client = $this->createClient(
            $expectedRequests,
            $expectedMethod,
            $expectedUrl,
            $expectedOptions,
            hasExpiredToken: true,
        );

        $client->get("/some_endpoint", ["filter[field]" => "value"]);
    }
}
