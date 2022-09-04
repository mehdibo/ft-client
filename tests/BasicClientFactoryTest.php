<?php

namespace Mehdibo\FortyTwo\Client\Test;

use Mehdibo\FortyTwo\Client\BasicClient;
use Mehdibo\FortyTwo\Client\BasicClientFactory;
use Mehdibo\OAuth2\Client\Provider\FortyTwo;
use PHPUnit\Framework\TestCase;

class BasicClientFactoryTest extends TestCase
{
    public function testCreateFromCredentials(): void
    {
        $client = BasicClientFactory::createFromCredentials('client_id', 'client_secret', 'redirect_uri');
        $this->assertInstanceOf(BasicClient::class, $client);
        $expectedProvider = new FortyTwo([
            'clientId' => "client_id",
            'clientSecret' => "client_secret",
            'redirectUri' => "redirect_uri",
        ]);
        $this->assertEquals($expectedProvider, $client->getProvider());
    }

    public function testCreateFromProvider(): void
    {
        $provider = new FortyTwo([
            'clientId' => "client_id",
            'clientSecret' => "client_secret",
            'redirectUri' => "redirect_uri",
        ]);
        $client = BasicClientFactory::createFromProvider($provider);
        $this->assertInstanceOf(BasicClient::class, $client);
        $this->assertEquals($provider, $client->getProvider());
    }
}
