<?php

namespace Drewlabs\Psr18;

use Drewlabs\Psr18\ClientOptions;
use Drewlabs\Psr7\CreatesJSONStream;
use Drewlabs\Psr7\CreatesMultipartStream;
use Drewlabs\Psr7\CreatesURLEncodedStream;
use Drewlabs\Psr7\Uri;
use Drewlabs\Psr7Stream\LazyStream;
use Psr\Http\Message\RequestInterface;

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
        $optionsBody = $requestOptions->getBody() ?? [];
        if (!empty($contentTypeHeader) && preg_match('/^multipart\/form-data/', $contentTypeHeader) && !empty($optionsBody)) {
            // Handle request of multipart http request
            $createsStream = new CreatesMultipartStream($optionsBody);
            $body = new LazyStream($createsStream);
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $createsStream->getBoundary();
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
}
