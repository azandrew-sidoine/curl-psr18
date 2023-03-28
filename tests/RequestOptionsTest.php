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

use Drewlabs\Psr18\RequestOptions;
use PHPUnit\Framework\TestCase;

class RequestOptionsTest extends TestCase
{
    public function test_request_client_static_create()
    {
        $request = RequestOptions::create([]);

        $this->assertInstanceOf(RequestOptions::class, $request);
    }

    public function test_request_options_create_set_user_provided_attributes()
    {
        $request = RequestOptions::create([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
            'auth' => ['MyUser', 'MyPassword', 'digest'],
            'query' => [
                'post_id' => 2, 'comments_count' => 1,
            ],
            'encoding' => 'gzip,deflate',
        ]);

        $this->assertSame(['Content-Type' => 'application/json'], $request->getHeaders());
        $this->assertSame(10, $request->getTimeout());
        $this->assertSame(['MyUser', 'MyPassword', 'digest'], $request->getAuth());
        $this->assertSame(['post_id' => 2, 'comments_count' => 1], $request->getQuery());
        $this->assertSame('gzip,deflate', $request->getEncoding());
    }

    public function test_request_query_setters()
    {
        $request = new RequestOptions();
        $request->setAuth('MyUser', 'MyPassword', 'basic');
        $request->setHeaders(['Accept-Encoding' => 'gzip,deflate']);
        $request->setTimeout(12);

        $this->assertSame(['Accept-Encoding' => 'gzip,deflate'], $request->getHeaders());
        $this->assertSame(['MyUser', 'MyPassword', 'basic'], $request->getAuth());
        $this->assertSame(12, $request->getTimeout());
    }

    public function test_request_option_set_headers_throughs_exception_for_non_dictionary_array()
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = new RequestOptions();
        $request->setHeaders(['application/json', 'gzip,deflate']);
    }
}
