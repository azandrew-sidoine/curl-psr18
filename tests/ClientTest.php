<?php

declare(strict_types=1);

/*
 * This file is part of the Drewlabs package.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use donatj\MockWebServer\DelayedResponse;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use donatj\MockWebServer\Responses\NotFoundResponse;
use Drewlabs\Curl\Mock\PostRequestResponse;
use Drewlabs\Curl\Psr18Client;
use Drewlabs\Psr7\Request;
use Drewlabs\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

if (version_compare(\PHP_VERSION, '7.1.0') >= 0) {
    class ClientTest extends TestCase
    {
        /** @var MockWebServer */
        protected static $server;

        #[\ReturnTypeWillChange]
        public static function setUpBeforeClass(): void
        {
            self::$server = new MockWebServer();
            // The default response is donatj\MockWebServer\Responses\DefaultResponse
            // which returns an HTTP 200 and a descriptive JSON payload.
            //
            // Change the default response to donatj\MockWebServer\Responses\NotFoundResponse
            // to get a standard 404.
            //
            // Any other response may be specified as default as well.
            self::$server->setDefaultResponse(new NotFoundResponse());
            self::$server->start();

        }

        public static function tearDownAfterClass(): void
        {
            // stopping the web server during tear down allows us to reuse the port for later tests
            self::$server->stop();

        }

        public function test_psr18_client_create_new_instance()
        {
            $client = Psr18Client::new();
            $this->assertInstanceOf(ClientInterface::class, $client);
        }

        public function test_ps18_client_override_request()
        {
            $client = Psr18Client::new(null, [
                'base_url' => 'http://127.0.0.1:3000',
                'request' => [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept-Encoding' => 'gzip,deflate',
                    ],
                    'timeout' => 10,
                    'auth' => ['MyUser', 'MyPassword', 'digest'],
                    'query' => [
                        'post_id' => 2, 'comments_count' => 1,
                    ],
                ],
            ]);

            $request = $client->overrideRequest(new Request('GET', 'http://127.0.0.1:80'));
            $uri = $request->getUri();
            $expects = ($uri->getScheme() ?? 'http').'://'.rtrim($uri->getHost().(!empty($p = $uri->getPort()) ? ":$p" : ''), '/');
            $this->assertEquals('http://127.0.0.1:3000', $expects);
            $this->assertEquals(['application/json'], $request->getHeader('content-type'));
            $this->assertEquals(['gzip,deflate'], $request->getHeader('Accept-Encoding'));
            $this->assertEquals('post_id=2&comments_count=1', $request->getUri()->getQuery());
        }

        public function test_psr18_client_get_request()
        {
            $expects = [
                'id' => 2,
                'reference' => 'TR-98249LBN8724',
                'status' => 0,
                'processors' => [],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $url = self::$server->setResponseOfPath(
                '/api/transactions',
                new DelayedResponse(
                    new Response(json_encode($expects)),
                    1000
                )
            );
            $requestURI = (new Uri($url));
            $client = Psr18Client::new(($requestURI->getScheme() ?? 'http').'://'.rtrim($requestURI->getHost().(!empty($p = $requestURI->getPort()) ? ":$p" : ''), '/').'/api/transactions', [
                'verify' => false,
                'request' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'transaction_id' => 2,
                    ],
                ],
            ]);
            $response = $client->sendRequest(new Request('GET', ''));
            $this->assertEquals($expects, json_decode($response->getBody()->__toString(), true));
        }

        public function test_psr18_client_send_post_json_request()
        {
            $url = self::$server->setResponseOfPath('/tests/post', new PostRequestResponse());
            $requestURI = (new Uri($url));
            $expects = [
                'post_id' => 2,
                'comments' => [
                    ['content' => 'Hello World!', 'likes' => 0],
                    ['content' => 'Testing implementation of Psr7 client!', 'likes' => 5],
                ],
            ];
            $client = Psr18Client::new(($requestURI->getScheme() ?? 'http').'://'.rtrim($requestURI->getHost().(!empty($p = $requestURI->getPort()) ? ":$p" : ''), '/').'/tests/post', [
                'verify' => false,
                'request' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => $expects,
                ],
            ]);
            $response = $client->sendRequest(new Request('POST'));
            $this->assertEquals($expects, json_decode($response->getBody()->__toString(), true));
        }

        public function test_psr18_client_send_post_multipart_request()
        {
            $url = self::$server->setResponseOfPath('/tests/post', new PostRequestResponse());
            $requestURI = (new Uri($url));
            $requests = require __DIR__.'/requests.php';
            $expects = [
                'post_id' => 2,
                'comments' => [
                    ['content' => 'Hello World!', 'likes' => 0],
                    ['content' => 'Testing implementation of Psr7 client!', 'likes' => 5],
                ],
            ];
            $client = Psr18Client::new(($requestURI->getScheme() ?? 'http').'://'.rtrim($requestURI->getHost().(!empty($p = $requestURI->getPort()) ? ":$p" : ''), '/').'/tests/post', [
                'verify' => false,
                'request' => [
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                        'Accept' => 'application/json',
                    ],
                    'body' => $requests['multipart2'],
                ],
            ]);
            $response = $client->sendRequest(new Request('POST'));
            $this->assertEquals($expects, json_decode($response->getBody()->__toString(), true));
        }

        public function test_psr18_client_send_post_url_encoded_request()
        {
            $url = self::$server->setResponseOfPath('/test/post', new PostRequestResponse());
            $requestURI = (new Uri($url));
            $requests = require __DIR__.'/requests.php';
            $expects = [
                'post_id' => 2,
                'comments' => [
                    ['content' => 'Hello World!', 'likes' => 0],
                    ['content' => 'Testing implementation of Psr7 client!', 'likes' => 5],
                ],
            ];
            $client = Psr18Client::new(($requestURI->getScheme() ?? 'http').'://'.rtrim($requestURI->getHost().(!empty($p = $requestURI->getPort()) ? ":$p" : ''), '/').'/tests/post', [
                'verify' => false,
                'request' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => $requests['body2'],
                ],
            ]);
            $response = $client->sendRequest(new Request('POST'));
            $this->assertEquals($expects, json_decode($response->getBody()->__toString(), true));
        }

        public function test_psr18_client_return_404_response_if_server_return_not_found_response()
        {
            $url = self::$server->setResponseOfPath('/test/post', new PostRequestResponse());
            $requestURI = (new Uri($url));
            $requests = require __DIR__.'/requests.php';
            $client = Psr18Client::new(($requestURI->getScheme() ?? 'http').'://'.rtrim($requestURI->getHost().(!empty($p = $requestURI->getPort()) ? ":$p" : ''), '/').'/404', [
                'verify' => false,
                'request' => [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => $requests['body2'],
                ],
            ]);
            $response = $client->sendRequest(new Request('POST'));
            $this->assertEquals(404, $response->getStatusCode());
        }
    }
}
