# ft-client
[![Latest Stable Version](http://poser.pugx.org/mehdibo/ft-client/v)](https://packagist.org/packages/mehdibo/ft-client)
[![Latest Unstable Version](http://poser.pugx.org/mehdibo/ft-client/v/unstable)](https://packagist.org/packages/mehdibo/ft-client)
[![License](http://poser.pugx.org/mehdibo/ft-client/license)](https://packagist.org/packages/mehdibo/ft-client)
[![Total Downloads](http://poser.pugx.org/mehdibo/ft-client/downloads)](https://packagist.org/packages/mehdibo/ft-client)
[![PHP Version Require](http://poser.pugx.org/mehdibo/ft-client/require/php)](https://packagist.org/packages/mehdibo/ft-client)
![Unit tests](https://github.com/mehdibo/oauth2-fortytwo/workflows/Unit%20tests/badge.svg?branch=main)

Client library to consume the 42 Intranet's API

## Installation
```bash
composer require mehdibo/ft-client
```

## Usage examples

- [Using the Authorization Code grant](#using-the-authorization-code-grant)
- [Using the Client Credentials grant](#using-the-client-credentials-grant)
- [Enumerating pages](#enumerating-pages)

### Using the Authorization Code grant
```php
include 'vendor/autoload.php';

$client = \Mehdibo\FortyTwo\Client\BasicClientFactory::createFromCredentials(
    'CLIENT_ID',
    'CLIENT_SECRET',
    'REDIRECT_URI'
);

$client->fetchTokenFromAuthCode($_GET['code']);
$user = $client->get("/me");
```

### Using the Client Credentials grant
```php
include 'vendor/autoload.php';

$client = \Mehdibo\FortyTwo\Client\BasicClientFactory::createFromCredentials(
    'CLIENT_ID',
    'CLIENT_SECRET',
    'REDIRECT_URI'
);

// This is not necessary, if no token was fetched it will automatically fetch one using the Client Credentials grant
$client->fetchTokenFromClientCredentials();

$cute = $client->get("/users/norminet");
```

### Enumerating pages
This client comes with a method to easily enumerate pages of a paginated API endpoint.
```php
include 'vendor/autoload.php';

$client = \Mehdibo\FortyTwo\Client\BasicClientFactory::createFromCredentials(
    'CLIENT_ID',
    'CLIENT_SECRET',
    'REDIRECT_URI'
);

$users = $client->enumerate("/users", [
    'sort' => '-id',
]);

try {
    foreach ($users as $user) {
        echo $user['login'] . PHP_EOL;
    }
} catch (\Mehdibo\FortyTwo\Client\Exception\EnumerationRateLimited $e) {
    echo "Rate limited, retry in " . $e->retryAfter . " seconds\n";
    echo "Stopped at page " . $e->reachedPage . "\n";
}
```
