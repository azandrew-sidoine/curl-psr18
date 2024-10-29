<?php

namespace Drewlabs\Psr18;

use Drewlabs\Psr18\ClientOptions;
use Drewlabs\Psr7\CreatesJSONStream;
use Drewlabs\Psr7\CreatesMultipartStream;
use Drewlabs\Psr7\CreatesURLEncodedStream;
use Drewlabs\Psr7\Uri;
use Drewlabs\Psr7Stream\LazyStream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class OverrideRequest
{
    /**
     * 
     * @var ClientOptions
     */
    private $options;

    public function __construct(ClientOptions $options)
    {
        $this->options = $options;
    }

    public function __invoke(RequestInterface $request)
    {
        $clientOptions = $clientOptions ?? $this->options;
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

        // Set the content type header if provided
        $headers['Content-Type'] = $contentTypeHeader;

        // Override request content type and body of request options has body configuration
        if (!empty($optionsBody = $requestOptions->getBody() ?? [])) {
            $this->writeRequestBodyContentTypeHeader($optionsBody, $contentTypeHeader, $body, $headers);
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
     * Rewrite request body and content type header for the options body configuration
     * @param mixed $override 
     * @param string|null $contentType
     * @param StreamInterface &$body 
     * @param array &$headers 
     * @return void 
     */
    private function writeRequestBodyContentTypeHeader($override, string $contentType, StreamInterface &$body, array &$headers)
    {
        if (!empty($contentType) && preg_match('/^multipart\/form-data/', $contentType)) {
            // Handle request of multipart http request
            $createsStream = new CreatesMultipartStream($override);
            $body = new LazyStream($createsStream);
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $createsStream->getBoundary();
            return;
        }

        if (!empty($contentType) && preg_match('/^(application|text)\/json/i', $contentType)) {
            // Handle JSON request
            $body = new LazyStream(new CreatesJSONStream($override));
            $headers['Content-Type'] = 'application/json';
            return;
        }

        if (!empty($contentType) && preg_match('/^application\/x-www-form-urlencoded/i', $contentType)) {
            // Handle URL encoded request
            $body = new LazyStream(new CreatesURLEncodedStream($override));
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            return;
        }
    }
}
