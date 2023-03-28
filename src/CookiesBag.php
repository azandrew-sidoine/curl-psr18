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

class CookiesBag
{
    /**
     * @var string[]
     */
    public const RFC2616 = [
        // RFC 2616: "any CHAR except CTLs or separators".
        // CHAR           = <any US-ASCII character (octets 0 - 127)>
        // CTL            = <any US-ASCII control character
        //                  (octets 0 - 31) and DEL (127)>
        // separators     = "(" | ")" | "<" | ">" | "@"
        //                | "," | ";" | ":" | "\" | <">
        //                | "/" | "[" | "]" | "?" | "="
        //                | "{" | "}" | SP | HT
        // SP             = <US-ASCII SP, space (32)>
        // HT             = <US-ASCII HT, horizontal-tab (9)>
        // <">            = <US-ASCII double-quote mark (34)>
        '!', '#', '$', '%', '&', "'", '*', '+', '-', '.', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B',
        'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q',
        'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '|', '~',
    ];

    /**
     * @var string[]
     */
    public const RFC6265 = [
        // RFC 6265: "US-ASCII characters excluding CTLs, whitespace DQUOTE, comma, semicolon, and backslash".
        // %x21
        '!',
        // %x23-2B
        '#', '$', '%', '&', "'", '(', ')', '*', '+',
        // %x2D-3A
        '-', '.', '/', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':',
        // %x3C-5B
        '<', '=', '>', '?', '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q',
        'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[',
        // %x5D-7E
        ']', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~',
    ];
    /**
     * @var array<string,string>
     */
    private $cookies = [];

    /**
     * Creates an instance of the {@see Cookies} object.
     */
    public function __construct(array $cookies = [])
    {
        $this->setCookies($cookies);
    }

    /**
     *  Add a cookie entry.
     *
     * @return static
     */
    public function set(string $name, string $value)
    {
        $cookieName = implode('', array_map(static function ($char) {
            return isset(self::RFC2616[$char]) ? $char : rawurlencode($char);
        }, str_split($name)));
        $cookieValue = implode('', array_map(static function ($char) {
            return isset(self::RFC6265[$char]) ? $char : rawurlencode($char);
        }, str_split($value)));

        $this->cookies[$cookieName] = $cookieValue;

        return $this;
    }

    /**
     * Query for a cookie value.
     *
     * @return string|null
     */
    public function get(string $name)
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Checks if a given cookie exists.
     *
     * @return bool
     */
    public function has(string $name)
    {
        return \array_key_exists($name, $this->cookies);
    }

    /**
     * Returns the list of cookies.
     *
     * @return array<string,string>
     */
    public function toArray()
    {
        return $this->cookies;
    }

    /**
     * Remove the matching cookie.
     */
    public function remove(string $name)
    {
        unset($this->cookies[$name]);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return null !== $this->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Add a list of cookies to the cookie store.
     *
     * @param mixed $cookies
     *
     * @return void
     */
    public function setCookies($cookies)
    {
        foreach ($cookies as $key => $value) {
            $this->set($key, $value);
        }
    }
}
