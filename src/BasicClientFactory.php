<?php

namespace Mehdibo\FortyTwo\Client;

use Mehdibo\OAuth2\Client\Provider\FortyTwo;

class BasicClientFactory
{
    public static function createFromCredentials(string $clientId, string $clientSecret, string $redirectUri): BasicClient
    {
        $provider = new FortyTwo([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
        ]);
        return new BasicClient($provider);
    }

    public static function createFromProvider(FortyTwo $provider): BasicClient
    {
        return new BasicClient($provider);
    }
}
