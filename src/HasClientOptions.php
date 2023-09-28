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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array
     */
    private function appendClientOptions(RequestInterface $request, ClientOptions $options, array $output)
    {
        if (null !== ($verify = $options->getVerify())) {
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

        $requestOptions = $options->getRequest() ?? new RequestOptions();

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
        if ($cookies = $options->getCookies()) {
            $cookies = \is_array($cookies) ? $cookies : ($cookies ? $cookies->toArray() : []);
            $output[\CURLOPT_COOKIE] = implode('; ', array_map(static function ($key, $value) {
                return $key.'='.$value;
            }, array_keys($cookies), array_values($cookies)));
        }

        $sink = $options->getSink();
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

        if ($sink && $options) {
            $this->setOptions($options->withSink($sink));
        }

        $output[\CURLOPT_WRITEFUNCTION] = static function ($ch, $write) use ($sink) {
            return $sink->write($write);
        };

        // CURL default value is CURL_IPRESOLVE_WHATEVER
        if ($ip = $options->getForceResolveIp()) {
            if ('v4' === $ip) {
                $output[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V4;
            } elseif ('v6' === $ip) {
                $output[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V6;
            }
        }

        if ($connectTimeout = $options->connectTimeout()) {
            $timeoutRequiresNoSignal |= $connectTimeout < 1;
            $output[\CURLOPT_CONNECTTIMEOUT_MS] = $connectTimeout * 1000;
        }

        if ($timeoutRequiresNoSignal && 'WIN' !== strtoupper(substr(\PHP_OS, 0, 3))) {
            $output[\CURLOPT_NOSIGNAL] = true;
        }

        if ($proxy = $options->getProxy()) {
            $output[\CURLOPT_PROXY] = $proxy[0];
            if (isset($proxy[1])) {
                $output[\CURLOPT_PROXYPORT] = $proxy[1];
            }
            if (isset($proxy[2], $proxy[3])) {
                $output[\CURLOPT_PROXYUSERPWD] = $proxy[2].':'.$proxy[3];
            }
        }

        if ($cert = $options->getCert()) {
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

        if ($sslKeyOptions = $options->getSslKey()) {
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

        if ($progress = $options->getProgress()) {
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
