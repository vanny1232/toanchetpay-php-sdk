<?php

declare(strict_types=1);

namespace ToanchetPay\Http;

use ToanchetPay\Exceptions\HttpException;

class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;
    private bool $verifySsl;

    /** @var array<string,string> */
    private array $defaultHeaders;

    /**
     * @param array<string,string> $defaultHeaders
     */
    public function __construct(
        int $timeout = 30,
        bool $verifySsl = true,
        array $defaultHeaders = []
    ) {
        $this->timeout        = $timeout;
        $this->verifySsl      = $verifySsl;
        $this->defaultHeaders = $defaultHeaders;
    }

    public function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new HttpException('Failed to JSON-encode request payload.');
        }

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], $this->defaultHeaders);

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            throw new HttpException("cURL error ({$errno}): {$error}", $errno);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new HttpException(
                "HTTP {$httpCode} received from ACLEDA API. Response: " . substr((string) $response, 0, 500),
                $httpCode
            );
        }

        $decoded = json_decode((string) $response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(
                'Failed to decode JSON response: ' . json_last_error_msg() . '. Raw: ' . substr((string) $response, 0, 200)
            );
        }

        return (array) $decoded;
    }
}
