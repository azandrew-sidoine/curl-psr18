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

use Drewlabs\Psr18\ClientOptions;
use Drewlabs\Psr18\CookiesBag;
use Drewlabs\Psr18\RequestOptions;
use PHPUnit\Framework\TestCase;

class ClientOptionsTest extends TestCase
{
    public function test_client_options_static_create()
    {
        $options = ClientOptions::create([]);
        $this->assertInstanceOf(ClientOptions::class, $options);
    }

    public function test_client_options_create_with_attributes()
    {
        $options = ClientOptions::create([
            'verify' => false,
            'sink' => null,
            'force_resolve_ip' => false,
            'proxy' => ['http://proxy.app-ip.com'],
            'cert' => null,
            'ssl_key' => ['/home/webhost/.ssh/pub.key'],
            'progress' => new class() {
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
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10,
                'auth' => ['MyUser', 'MyPassword', 'digest'],
                'query' => [
                    'post_id' => 2, 'comments_count' => 1,
                ],
                'encoding' => 'gzip,deflate',
            ],
            'cookies' => [
                'clientid' => 'myClientID', 'clientsecret' => 'MySuperSecret',
            ],
        ]);
        $this->assertSame('http://127.0.0.1:3000', $options->getBaseURL());
        $this->assertSame(['http://proxy.app-ip.com'], $options->getProxy());
        $this->assertInstanceOf(RequestOptions::class, $options->getRequest());
        $this->assertSame(['Content-Type' => 'application/json'], $options->getRequest()->getHeaders());
        $this->assertSame(10, $options->getRequest()->getTimeout());
        $this->assertSame(['MyUser', 'MyPassword', 'digest'], $options->getRequest()->getAuth());
        $this->assertSame(['post_id' => 2, 'comments_count' => 1], $options->getRequest()->getQuery());
        $this->assertSame('gzip,deflate', $options->getRequest()->getEncoding());
        $this->assertInstanceOf(CookiesBag::class, $options->getCookies());
        $this->assertSame('myClientID', $options->getCookies()->get('clientid'));
        $this->assertSame('MySuperSecret', $options->getCookies()->get('clientsecret'));
        $this->assertFalse($options->getForceResolveIp());
        $this->assertFalse($options->getVerify());
    }
}
