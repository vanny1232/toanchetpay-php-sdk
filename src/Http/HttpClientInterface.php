<?php

declare(strict_types=1);

namespace ToanchetPay\Http;

use ToanchetPay\Exceptions\HttpException;

interface HttpClientInterface
{
    /**
     * Send a POST request with a JSON body and return the decoded response.
     *
     * @param  string               $url
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     *
     * @throws HttpException
     */
    public function postJson(string $url, array $payload): array;
}
