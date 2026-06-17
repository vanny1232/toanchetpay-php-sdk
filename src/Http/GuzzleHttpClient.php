<?php

declare(strict_types=1);

namespace ToanchetPay\Http;

use ToanchetPay\Exceptions\HttpException;

/**
 * Optional Guzzle adapter.
 *
 * Requires: composer require guzzlehttp/guzzle:^7.0
 *
 * Usage:
 *   $guzzle = new \GuzzleHttp\Client();
 *   $http   = new GuzzleHttpClient($guzzle);
 *   $client = new ToanchetPayClient($config, $http);
 */
class GuzzleHttpClient implements HttpClientInterface
{
    /** @var \GuzzleHttp\Client */
    private $guzzle;

    /** @var array<string,mixed> */
    private array $defaultOptions;

    /**
     * @param \GuzzleHttp\Client   $guzzle
     * @param array<string,mixed>  $defaultOptions  Extra Guzzle request options.
     */
    public function __construct(object $guzzle, array $defaultOptions = [])
    {
        if (!$guzzle instanceof \GuzzleHttp\Client) {
            throw new \InvalidArgumentException(
                'GuzzleHttpClient expects an instance of GuzzleHttp\Client.'
            );
        }

        $this->guzzle         = $guzzle;
        $this->defaultOptions = $defaultOptions;
    }

    public function postJson(string $url, array $payload): array
    {
        try {
            $response = $this->guzzle->post($url, array_merge($this->defaultOptions, [
                'json'    => $payload,
                'headers' => ['Accept' => 'application/json'],
            ]));
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            throw new HttpException('Guzzle request failed: ' . $e->getMessage(), $statusCode, $e);
        } catch (\Throwable $e) {
            throw new HttpException('Guzzle request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpException(
                "HTTP {$statusCode} received from ACLEDA API.",
                $statusCode
            );
        }

        $body    = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return (array) $decoded;
    }
}
