<?php

namespace Mehdibo\FortyTwo\Client;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Mehdibo\FortyTwo\Client\Exception\EnumerationRateLimited;
use Mehdibo\FortyTwo\Client\Exception\RateLimitReached;
use Mehdibo\FortyTwo\Client\Exception\ServerError;
use Mehdibo\OAuth2\Client\Provider\FortyTwo;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * BasicClient allows you to send raw authenticated requests to the 42 API
 */
class BasicClient
{
    public const VERSION = "v1.0";

    private const BASE_URL = "https://api.intra.42.fr/v2/";

    private AccessTokenInterface|null $accessToken = null;

    private HttpClientInterface $httpClient;

    /**
     * @param FortyTwo $provider The OAuth2 provider for access tokens
     * @param HttpClientInterface|null $httpClient
     */
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

    public function withProvider(FortyTwo $provider): BasicClient
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
     * Fetch an access token using the BasicClient Credentials grant
     * @throws IdentityProviderException
     */
    public function fetchTokenFromClientCredentials(): void
    {
        $this->accessToken = $this->provider->getAccessToken("client_credentials");
    }

    /**
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
            // A refresh token is not provided with the BasicClient Credentials grant
            $this->fetchTokenFromClientCredentials();
            return;
        }
        $this->accessToken = $this->provider->getAccessToken("refresh_token", [
            "refresh_token" => $refreshToken,
        ]);
    }

    /**
     * Get token, fetch one if not already done and refresh it if expired
     * @return string
     * @throws IdentityProviderException
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
     * @throws TransportExceptionInterface|IdentityProviderException
     * @throws RateLimitReached
     * @throws ServerError
     */
    private function doRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        $uri = self::BASE_URL . ltrim($uri, "/");
        $options["headers"]["Authorization"] = "Bearer " . $this->getToken();
        $options["headers"]["User-Agent"] = "Mehdibo-FT-Client/".self::VERSION;
        $resp = $this->httpClient->request($method, $uri, $options);
        switch ($resp->getStatusCode()) {
            // Rate limit reached
            case 429:
                $retryAfter = $resp->getHeaders(false)["retry-after"][0] ?? null;
                throw new RateLimitReached($retryAfter === null ? null : (int)$retryAfter);
            case 500:
                throw new ServerError();
            default:
                return $resp;
        }
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
        $queryStr = http_build_query($query);
        if (!empty($queryStr)) {
            $uri .= "?". $queryStr;
        }
        return $uri;
    }

    /**
     * @param string $uri The URI to request, e.g. /projects_users
     * @param array<string, string> $query The query parameters to send with the request
     * @return ResponseInterface
     * @throws RateLimitReached
     * @throws IdentityProviderException
     * @throws TransportExceptionInterface
     * @throws ServerError
     */
    public function get(string $uri, array $query = []): ResponseInterface
    {
        $uri = $this->buildUri($uri, $query);
        return $this->doRequest("GET", $uri);
    }

    /**
     * @param string $uri
     * @param array<string|int, mixed> $payload
     * @return ResponseInterface
     * @throws RateLimitReached
     * @throws IdentityProviderException
     * @throws TransportExceptionInterface
     * @throws ServerError
     */
    public function post(string $uri, array $payload): ResponseInterface
    {
        return $this->doRequest("POST", $uri, ["json" => $payload]);
    }

    /**
     * @param string $uri
     * @param array<string|int, mixed> $payload
     * @return ResponseInterface
     * @throws RateLimitReached
     * @throws IdentityProviderException
     * @throws TransportExceptionInterface
     * @throws ServerError
     */
    public function patch(string $uri, array $payload): ResponseInterface
    {
        return $this->doRequest("PATCH", $uri, ["json" => $payload]);
    }

    /**
     * @param string $uri
     * @return ResponseInterface
     * @throws RateLimitReached
     * @throws IdentityProviderException
     * @throws TransportExceptionInterface
     * @throws ServerError
     */
    public function delete(string $uri): ResponseInterface
    {
        return $this->doRequest("DELETE", $uri);
    }


    /**
     * Enumerate a resource and return all the items by navigating all pages
     * @param array<string, string> $query
     * @param int $maxItems Maximum of items to return. If 0, all items will be returned
     * @param int $startPage Page to start from
     * @return \Generator<int, array<string, mixed>>
     * @throws IdentityProviderException
     * @throws TransportExceptionInterface
     * @throws ServerError
     * @throws EnumerationRateLimited
     */
    public function enumerate(string $uri, array $query = [], int $maxItems = 0, int $startPage = 1): \Generator
    {
        $page = ($startPage <= 0) ? 1 : $startPage;
        $perPage = ($maxItems !== 0 && $maxItems < 100) ? $maxItems : 100;
        $query["page"] = (string) $page;
        $query["per_page"] = (string) $perPage;
        $itemsCount = 0;
        do {
            try {
                $resp = $this->get($uri, $query);
            } catch (RateLimitReached $e) {
                throw new EnumerationRateLimited($page, $e);
            }
            $items = $resp->toArray();
            $page++;
            $query["page"] = (string) $page;
            foreach ($items as $item) {
                yield $item;
                $itemsCount++;
                if ($maxItems !== 0 && $itemsCount >= $maxItems) {
                    break 2;
                }
            }
        } while (count($items) >= $perPage && ($maxItems === 0 || $itemsCount < $maxItems));
    }
}
