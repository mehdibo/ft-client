<?php

namespace Mehdibo\FortyTwo\SDK;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Mehdibo\FortyTwo\SDK\Exception\MissingRefreshToken;
use Mehdibo\OAuth2\Client\Provider\FortyTwo;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Client
{
    public const VERSION = "v1.0.0";

    private const BASE_URL = "https://api.intra.42.fr/v2/";

    private ?AccessTokenInterface $accessToken = null;

    private HttpClientInterface $httpClient;

    public function __construct(
        private FortyTwo $provider,
        HttpClientInterface|null $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function getProvider(): FortyTwo
    {
        return $this->provider;
    }

    public function withProvider(FortyTwo $provider): Client
    {
        $client = clone $this;
        $client->provider = $provider;
        return $client;
    }

    public function getAccessToken(): AccessTokenInterface|null
    {
        return $this->accessToken;
    }

    /**
     * Fetch an access token using the Authorization Code grant
     * @throws IdentityProviderException
     */
    public function fetchTokenFromAuthCode(string $code): void
    {
        $this->accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);
    }

    /**
     * Fetch an access token using the Client Credentials grant
     * @throws IdentityProviderException
     */
    public function fetchTokenFromClientCredentials(): void
    {
        $this->accessToken = $this->provider->getAccessToken("client_credentials");
    }

    /**
     * @throws MissingRefreshToken
     * @throws IdentityProviderException
     */
    private function refreshToken(): void
    {
        $accessToken = $this->accessToken;
        if ($accessToken === null) {
            throw new \LogicException("Must not be called on a null access token");
        }
        $refreshToken = $this->accessToken?->getRefreshToken();
        if ($refreshToken === null) {
            throw new MissingRefreshToken();
        }
        $this->accessToken = $this->provider->getAccessToken("refresh_token", [
            "refresh_token" => $refreshToken,
        ]);
    }

    /**
     * Get token, fetch one if not already done and refresh it if expired
     * @return string
     * @throws IdentityProviderException
     * @throws MissingRefreshToken
     */
    private function getToken(): string
    {
        if ($this->accessToken === null) {
            $this->fetchTokenFromClientCredentials();
        }
        if ($this->accessToken?->hasExpired()) {
            $this->refreshToken();
        }
        if ($this->accessToken === null) {
            throw new \LogicException("Access token must not be null");
        }
        return $this->accessToken->getToken();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array<string, mixed> $options
     * @return ResponseInterface
     * @throws TransportExceptionInterface|IdentityProviderException|MissingRefreshToken
     */
    private function doRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        $uri = self::BASE_URL . ltrim($uri, "/");
        $options["headers"]["Authorization"] = "Bearer " . $this->getToken();
        $options["headers"]["User-Agent"] = "Mehdibo-FT-Client/".self::VERSION;
        // TODO: handle rate limit
        return $this->httpClient->request($method, $uri, $options);
    }

    /**
     * Create a complete URI from $uri and $query
     * @param string $uri
     * @param array<string, string> $query
     * @return string
     */
    private function buildUri(string $uri, array $query): string
    {
        $uri = rtrim($uri, '/');
        return $uri . "?" . http_build_query($query);
    }

    /**
     * @param string $uri The URI to request, e.g. /v2/projects_users
     * @param array<string, string> $query The query parameters to send with the request
     * @throws TransportExceptionInterface|IdentityProviderException|MissingRefreshToken
     * @return ResponseInterface
     */
    public function get(string $uri, array $query): ResponseInterface
    {
        $uri = $this->buildUri($uri, $query);
        return $this->doRequest("GET", $uri);
    }
}
