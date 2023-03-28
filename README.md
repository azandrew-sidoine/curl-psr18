# cURL PSR18 compatible client

This project provides a PSR18 client implementation based on the cURL client object.

## Installation

Recommended way to install the library is by using PHP package manager `composer` running the command below:

> composer require drewlabs/psr18

## Usage

### PSR18 Client

The package comes with a PSR18 compatible Client using the PHP cURL library. To creates an instance of the client:

```php
use Drewlabs\Psr18\Client;

// Creates an instance of the cURL client
$client = Client::new(/* Parameters */);

// Passing constructor parameters
$client = Client::new('http:://127.0.0.1:5000');

// Passing customize client options
$client = Client::new([
    /* Custom client options */
]);
```

### Client options

Client options, provide developpers with a way to override parameters passed to the `Client::sendRequest()` method. The package provide a PHP class for building client option as alternative to using PHP dictionary type (a.k.a PHP array).

- Creating the client options using a factory function

```php
use Drewlabs\Curl\ClientOptions;

//
$clientOptions = ClientOptions::create([
    'verify' => false,
    'sink' => null,
    'force_resolve_ip' => false,
    'proxy' => ['http://proxy.app-ip.com'],
    'cert' => null,
    'ssl_key' => ['/home/webhost/.ssh/pub.key'],
    'progress' => new class {
        // Declare the function to handle the progress event
        public function __invoke()
        {
            // Handle the progress event
        }
    },
    'base_url' => 'http://127.0.0.1:3000',
    'connect_timeout' => 1000,
    'request' => [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'timeout' => 10,
        'auth' => ['MyUser', 'MyPassword', 'digest'],
        'query' => [
            'post_id' => 2, 'comments_count' => 1
        ],
        'encoding' => 'gzip,deflate'
    ],
    'cookies' => [
        'clientid' => 'myClientID', 'clientsecret' => 'MySuperSecret'
    ],
]);
```

- Using the fluent API

Alternative to using the factory function, we can use the fluent API for creating a client options. The fluent API attemps to reduce developper typo errors by providing methods to defining option values:

```php
use Drewlabs\Curl\ClientOptions;
use Drewlabs\Curl\RequestOptions;

$clientOptions = new ClientOptions;

$clientOptions->setBaseURL(/* base url*/)
    ->setRequest(RequestOptions::create([]))
    ->setConnectTimeout(150)
    ->setVerify(false)
    // Psr Stream to write response output to
    ->setSink()
    ->setForceResolveIp(true)
    ->setProxy($proxy_ip, [$proxy_port, $user, $password]) // port, user & password are optional depending on the proxy configuration
    ->setCert('/path/to/ssl/certificate');
    ->setSslKey('/path/to/ssl/key')
    ->setProgress(function($curl, ...$progress) {
        // Handle cURL progress event
    })
    ->setCookies([]); // List of request cookies

```

**Note**
API for request options & client option fluent API can be found in the API reference documentation.

### Sending a PSR18 request

Sending request is simply as using any PSR18 compatible library:

```php
use Drewlabs\Psr18\Client;
use Drewlabs\Psr7\Request;

// Creates an instance of the cURL client
$client = Client::new([
    // Parameters to client options ...
        'base_url' => 'http://127.0.0.1:3000',
    'connect_timeout' => 1000,
    'request' => [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'timeout' => 10,
        'auth' => ['MyUser', 'MyPassword', 'digest'],
        'query' => [
            'post_id' => 2, 'comments_count' => 1
        ],
        'encoding' => 'gzip,deflate'
    ],
]);

$response = $client->sendRequest(new Request()); // \Psr7\Http\ResponseInterface
```

To send a JSON request, developpers call the `Client::json()` method before sending the request to the server:

```php
use Drewlabs\Psr18\Client;

// Creates an instance of the cURL client
$client = new Client(/* Parameters */);

// Sends a request with application/json as Content-Type
$client->json()->sendRequest(/* PSR7 compatible Request */);
```

Alternatively, to send a `multipart/form-data` request, developpers call the `Client::multipart()` method before sending the request to the server:

```php
use Drewlabs\Psr18\Client;

// Creates an instance of the cURL client
$client = new Client(/* Parameters */);

// Sends a request with application/json as Content-Type
$client->json()->sendRequest(new Request());
```
