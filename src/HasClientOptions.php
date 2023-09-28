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

use Drewlabs\Psr7\CreatesJSONStream;
use Drewlabs\Psr7\CreatesMultipartStream;
use Drewlabs\Psr7\CreatesURLEncodedStream;
use Drewlabs\Psr7\Uri;
use Drewlabs\Psr7Stream\LazyStream;
use Drewlabs\Psr7Stream\Stream;
use Psr\Http\Message\RequestInterface;

trait HasClientOptions
{
    /**
     * @var ClientOptions
     */
    private $options;

    /**
     * Returns the request client options.
     *
     * @return ClientOptions
     */
    private function getOptions()
    {
        return $this->options;
    }

    /**
     * Client options setter method.
     *
     * @return $this
     */
    private function setOptions(ClientOptions $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Override psr7 request with request options.
     *
     * @internal Please do not use externally. The method is made public for testing purpose
     *
     * @param ClientOptions $clientOptions
     *
     * @throws \InvalidArgumentException
     */
    public function overrideRequest(RequestInterface $request, ?ClientOptions $clientOptions = null): RequestInterface
    {
        $clientOptions = $clientOptions ?? $this->getOptions();
        $requestOptions = $clientOptions->getRequest();
        if (null === $requestOptions) {
            return $request;
        }
        $uri = $request->getUri();
        if (null !== ($baseURL = $clientOptions->getBaseURL())) {
            $tmpURI = Uri::new($baseURL);
            // We rebuild the original request query if the base url has changed
            $uri = $uri->withHost($tmpURI->getHost())
                ->withFragment($tmpURI->getFragment())
                ->withPath(empty($path = $tmpURI->getPath()) ? $uri->getPath() : $path)
                ->withPort(empty($port = $tmpURI->getPort()) ? $uri->getPort() : $port)
                ->withQuery(empty($query = $tmpURI->getQuery()) ? $uri->getQuery() : $query)
                ->withScheme(empty($sheme = $tmpURI->getScheme()) ? $uri->getScheme() : $sheme)
                ->withUserInfo(empty($userInfo = $tmpURI->getUserInfo()) ? $uri->getUserInfo() : $userInfo);
        }
        // Get the request uri in temparary variable and check later if it changes
        // to update the request query
        $body = $request->getBody();
        $contentTypeHeader = empty($result = $request->getHeader('Content-Type')) ? '' : implode(',', $result);

        if (!empty($headers = $requestOptions->getHeaders())) {
            if (array_keys($headers) === range(0, \count($headers) - 1)) {
                throw new \InvalidArgumentException('The headers array must have header name as keys.');
            }
        }
        // Find the content type header from the request option headers
        foreach ($headers as $key => $value) {
            if ('content-type' === strtolower($key)) {
                $contentTypeHeader = (string) $value;
            }
        }
        $optionsBody = $requestOptions->getBody() ?? [];
        if (!empty($contentTypeHeader) && preg_match('/^multipart\/form-data/', $contentTypeHeader) && !empty($optionsBody)) {
            // Handle request of multipart http request
            $createsStream = new CreatesMultipartStream($optionsBody);
            $body = new LazyStream($createsStream);
            $headers['Content-Type'] = 'multipart/form-data; boundary='.$createsStream->getBoundary();
        } elseif (!empty($contentTypeHeader) && preg_match('/^(application|text)\/json/i', $contentTypeHeader)) {
            // Handle JSON request
            $body = new LazyStream(new CreatesJSONStream($optionsBody));
            $headers['Content-Type'] = 'application/json';
        } else if (!empty($contentTypeHeader) && preg_match('/^application\/x-www-form-urlencoded/i', $contentTypeHeader)) {
            // Handle URL encoded request
            $body = new LazyStream(new CreatesURLEncodedStream($optionsBody));
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        } else {
            $headers['Content-Type'] = $contentTypeHeader;
        }
        if (!empty($query = $requestOptions->getQuery())) {
            if (\is_array($query)) {
                $query = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
            }
            if (!\is_string($query)) {
                throw new \InvalidArgumentException('query must be a string or array');
            }
            $uri = $uri->withQuery($query);
        }

        if (!empty($encoding = $requestOptions->getEncoding()) && true !== $encoding) {
            // Ensure that we don't have the header in different case and set the new value.
            $headers['Accept-Encoding'] = $encoding;
        }

        if ($uri !== $request->getUri()) {
            $request = $request->withUri($uri);
        }

        if (!empty($headers)) {
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if ($body !== $request->getBody()) {
            $request = $request->withBody($body);
        }

        return $request;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array
     */
    private function appendClientOptions(RequestInterface $request, ClientOptions $clientOptions, array $output)
    {
        if (null !== ($verify = $clientOptions->getVerify())) {
            if (false === $verify) {
                unset($output[\CURLOPT_CAINFO]);
                $output[\CURLOPT_SSL_VERIFYHOST] = 0;
                $output[\CURLOPT_SSL_VERIFYPEER] = false;
            } else {
                $output[\CURLOPT_SSL_VERIFYHOST] = 2;
                $output[\CURLOPT_SSL_VERIFYPEER] = true;
                if (\is_string($verify)) {
                    if (!file_exists($verify)) {
                        throw new \InvalidArgumentException("SSL CA bundle not found: {$verify}");
                    }
                    // If it's a directory or a link to a directory use CURLOPT_CAPATH.
                    // If not, it's probably a file, or a link to a file, so use CURLOPT_CAINFO.
                    if (
                        is_dir($verify) ||
                        (
                            true === is_link($verify) &&
                            ($verifyLink = readlink($verify)) !== false &&
                            is_dir($verifyLink)
                        )
                    ) {
                        $output[\CURLOPT_CAPATH] = $verify;
                    } else {
                        $output[\CURLOPT_CAINFO] = $verify;
                    }
                }
            }
        }

        $requestOptions = $clientOptions->getRequest() ?? new RequestOptions();

        // Request Timeout
        $timeoutRequiresNoSignal = false;
        if ($timeout = $requestOptions->getTimeout()) {
            $timeoutRequiresNoSignal |= $timeout < 1;
            $output[\CURLOPT_TIMEOUT_MS] = $timeout * 1000;
        }

        // Request encoding
        if ($requestOptions->getEncoding()) {
            if ($accept = $request->getHeaderLine('Accept-Encoding')) {
                $output[\CURLOPT_ENCODING] = $accept;
            } else {
                $output[\CURLOPT_ENCODING] = '';
                $output[\CURLOPT_HTTPHEADER][] = 'Accept-Encoding:';
            }
        }

        // Request cookies
        if ($cookies = $clientOptions->getCookies()) {
            $cookies = \is_array($cookies) ? $cookies : ($cookies ? $cookies->toArray() : []);
            $output[\CURLOPT_COOKIE] = implode('; ', array_map(static function ($key, $value) {
                return $key.'='.$value;
            }, array_keys($cookies), array_values($cookies)));
        }

        $sink = $clientOptions->getSink();
        if (!\is_string($sink)) {
            $sink = Stream::new($sink ?? '');
        } elseif (!is_dir(\dirname($sink))) {
            // Ensure that the directory exists before failing in curl.
            throw new \RuntimeException(sprintf('Directory %s does not exist for sink value of %s', \dirname($sink), $sink));
        } else {
            $sink = new LazyStream(static function () use ($sink) {
                return Stream::new($sink, 'w+');
            });
        }

        // Should normally never happen
        if (null === $sink) {
            $sink = Stream::new('', 'w+');
        }

        if ($sink && $clientOptions) {
            $this->setOptions($clientOptions->withSink($sink));
        }

        $output[\CURLOPT_WRITEFUNCTION] = static function ($ch, $write) use ($sink) {
            return $sink->write($write);
        };

        // CURL default value is CURL_IPRESOLVE_WHATEVER
        if ($ip = $clientOptions->getForceResolveIp()) {
            if ('v4' === $ip) {
                $output[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V4;
            } elseif ('v6' === $ip) {
                $output[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V6;
            }
        }

        if ($connectTimeout = $clientOptions->connectTimeout()) {
            $timeoutRequiresNoSignal |= $connectTimeout < 1;
            $output[\CURLOPT_CONNECTTIMEOUT_MS] = $connectTimeout * 1000;
        }

        if ($timeoutRequiresNoSignal && 'WIN' !== strtoupper(substr(\PHP_OS, 0, 3))) {
            $output[\CURLOPT_NOSIGNAL] = true;
        }

        if ($proxy = $clientOptions->getProxy()) {
            $output[\CURLOPT_PROXY] = $proxy[0];
            if (isset($proxy[1])) {
                $output[\CURLOPT_PROXYPORT] = $proxy[1];
            }
            if (isset($proxy[2], $proxy[3])) {
                $output[\CURLOPT_PROXYUSERPWD] = $proxy[2].':'.$proxy[3];
            }
        }

        if ($cert = $clientOptions->getCert()) {
            $certFile = $cert[0];
            if (2 === \count($cert)) {
                $output[\CURLOPT_SSLCERTPASSWD] = $cert[1];
            }
            if (!file_exists($certFile)) {
                throw new \InvalidArgumentException("SSL certificate not found: {$certFile}");
            }
            // OpenSSL (versions 0.9.3 and later) also support "P12" for PKCS#12-encoded files.
            // see https://curl.se/libcurl/c/CURLOPT_SSLCERTTYPE.html
            $ext = pathinfo($certFile, \PATHINFO_EXTENSION);
            if (preg_match('#^(der|p12)$#i', $ext)) {
                $output[\CURLOPT_SSLCERTTYPE] = strtoupper($ext);
            }
            $output[\CURLOPT_SSLCERT] = $certFile;
        }

        if ($sslKeyOptions = $clientOptions->getSslKey()) {
            if (2 === \count($sslKeyOptions)) {
                [$sslKey, $output[\CURLOPT_SSLKEYPASSWD]] = $sslKeyOptions;
            } else {
                [$sslKey] = $sslKeyOptions;
            }
            if (!file_exists($sslKey)) {
                throw new \InvalidArgumentException("SSL private key not found: {$sslKey}");
            }
            $output[\CURLOPT_SSLKEY] = $sslKey;
        }

        if ($progress = $clientOptions->getProgress()) {
            if (!\is_callable($progress)) {
                throw new \InvalidArgumentException('progress client option must be callable');
            }
            $output[\CURLOPT_NOPROGRESS] = false;
            $output[\CURLOPT_PROGRESSFUNCTION] = static function ($resource, int $downloadSize, int $downloaded, int $uploadSize, int $uploaded) use ($progress) {
                $progress($downloadSize, $downloaded, $uploadSize, $uploaded);
            };
        }

        return $output;
    }

    private function applyAuthOptions(RequestOptions $requestOptions, array $output)
    {
        if (!empty($auth = $requestOptions->getAuth()) && \is_array($auth)) {
            $type = isset($auth[2]) ? strtolower($auth[2]) : 'basic';
            switch ($type) {
                case 'basic':
                    $output['__HEADERS__']['Authorization'] = 'Basic '.base64_encode("$auth[0]:$auth[1]");
                    break;
                case 'digest':
                    // TODO: In future release, find an implementation that build a digest auth algorithm
                    $output['curl'][\CURLOPT_HTTPAUTH] = \CURLAUTH_DIGEST;
                    $output['curl'][\CURLOPT_USERPWD] = "$auth[0]:$auth[1]";
                    break;
                case 'ntlm':
                    $output['curl'][\CURLOPT_HTTPAUTH] = \CURLAUTH_NTLM;
                    $output['curl'][\CURLOPT_USERPWD] = "$auth[0]:$auth[1]";
                    break;
            }
        }

        return $output;
    }
}
