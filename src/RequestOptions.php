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

namespace Drewlabs\Psr18;

class RequestOptions
{
    /**
     * @var int
     */
    private $timeout;

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var array
     */
    private $auth;

    /**
     * @var array|string|\JsonSerializable|object
     */
    private $body;

    /**
     * @var array|object
     */
    private $query;

    /**
     * @var string
     */
    private $encoding;

    /**
     * Creates a request options object.
     *
     * @param array $body
     * @param array $query
     */
    public function __construct(array $headers = [], $body = [], $query = [])
    {
        $this->headers = $headers;
        $this->body = $body;
        $this->query = $query;
    }

    /**
     * Create a new {@see \Drewlabs\Curl\ClientOptions} instance.
     *
     * **Note**
     *
     * ```php
     * $requestOptions = RequestOptions::create([
     *      'headers' => [
     *          'Content-Type': 'multipart/form-data',
     *      ],
     *      'body' => [
     *          // Request body ...
     *      ],
     *      'query' => [
     *          // Request query parameters ...
     *      ],
     *      'auth' => ['user', 'pass', 'basic'] // the 3rd option is optional. Defaults to basic,
     *      'encoding' => 'gzip,deflate'
     * ]);
     * ```
     *
     * @throws ReflectionException
     *
     * @return static
     */
    public static function create(array $properties = [])
    {
        if (\is_array($properties)) {
            /**
             * @var object
             */
            $instance = (new \ReflectionClass(__CLASS__))->newInstanceWithoutConstructor();
            foreach ($properties as $name => $value) {
                if (null === $value) {
                    continue;
                }
                // Tries to generate a camelcase method name from property name and prefix it with set
                if (method_exists($instance, $method = 'set'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name))))) {
                    \call_user_func([$instance, $method], $value);
                    continue;
                }
                if (property_exists($instance, $name)) {
                    $instance->{$name} = $value;
                    continue;
                }
            }

            return $instance;
        }

        return new static();
    }

    /**
     * Set the headers options.
     *
     * @return static
     */
    public function setHeaders(array $headers)
    {
        if (array_keys($headers) === range(0, \count($headers) - 1)) {
            throw new \InvalidArgumentException('Request headers must be a dictionary data type with key value binding');
        }
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get the headers options.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the request body options.
     *
     * @param array|string|\JsonSerializable|object $body
     *
     * @return static
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the request body with which default request is overriden.
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set the authentication options.
     *
     * @param string|array $user
     * @param string       $password
     * @param string       $type
     *
     * @return static
     */
    public function setAuth($user, ?string $password = null, $type = 'basic')
    {
        $this->auth = \is_array($user) ? $user : \func_get_args();

        return $this;
    }

    /**
     * Get the authentication options.
     *
     * @return array
     */
    public function getAuth()
    {
        return $this->auth ?? [];
    }

    /**
     * Set the request query parameter. This query parameter will override
     * the default query used in the psr7 request.
     *
     * @return static
     */
    public function setQuery(array $value)
    {
        $this->query = $value;

        return $this;
    }

    /**
     * Returns the request query parameter to use to append to the request
     * url by the request client.
     *
     * @return array|object
     */
    public function getQuery()
    {
        return (array) $this->query;
    }

    /**
     * Set the content encoding request option.
     *
     * @return static
     */
    public function setEncoding(string $value)
    {
        $this->encoding = $value;

        return $this;
    }

    /**
     * Get the content encoding request option.
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Set timeout configuration value.
     *
     * @return static
     */
    public function setTimeout(int $value)
    {
        $this->timeout = $value;

        return $this;
    }

    /**
     * Request timeout configuration value.
     *
     * @return int|false
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
}
