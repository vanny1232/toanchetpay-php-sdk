<?php

declare(strict_types=1);

namespace ToanchetPay\Client;

use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\TransactionStatusResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Http\CurlHttpClient;
use ToanchetPay\Http\HttpClientInterface;
use ToanchetPay\Security\HashGenerator;

/**
 * Main entry point for the ToanchetPay SDK.
 *
 * Quick start:
 *
 *   $config = new ToanchetPayConfig(
 *       merchantId:   'Zy8MFtBWdE67Thpurh2h4TSX7pw=',
 *       loginId:      'arakawauser',
 *       password:     'arakawauser',
 *       secretKey:    'YOUR_SECRET_KEY',
 *       merchantName: 'MERCHANTNAME'
 *   );
 *
 *   $client  = new ToanchetPayClient($config);
 *   $session = $client->openSession(ToanchetPayTransaction::standard(...));
 *   $status  = $client->getTransactionStatus($session->paymentTokenId);
 */
class ToanchetPayClient
{
    private ToanchetPayConfig         $config;
    private HashGenerator      $hashGenerator;
    private HttpClientInterface $http;

    public function __construct(ToanchetPayConfig $config, ?HttpClientInterface $http = null)
    {
        $this->config        = $config;
        $this->hashGenerator = new HashGenerator($config->getSecretKey());
        $this->http          = $http ?? new CurlHttpClient(
            $config->getTimeout(),
            $config->isVerifySsl()
        );
    }

    // -------------------------------------------------------------------------
    // openSessionV2
    // -------------------------------------------------------------------------

    /**
     * Open a payment session.
     *
     * @throws \ToanchetPay\Exceptions\ValidationException  Missing required fields.
     * @throws \ToanchetPay\Exceptions\HttpException        Network or HTTP error.
     * @throws ApiException                                 API returned non-zero code.
     */
    public function openSession(ToanchetPayTransaction $transaction): OpenSessionResponse
    {
        $hash = $this->hashGenerator->forSession(
            $this->config->getMerchantId(),
            $this->config->getLoginId(),
            $this->config->getPassword(),
            $transaction->getTxid()
        );

        $payload = [
            'loginId'          => $this->config->getLoginId(),
            'password'         => $this->config->getPassword(),
            'merchantID'       => $this->config->getMerchantId(),
            'hash'             => $hash,
            'toanchetpayTransaction'  => $transaction->toArray(),
        ];

        $response = $this->http->postJson(
            $this->config->getApiUrl('openSessionV2'),
            $payload
        );

        $this->assertApiSuccess($response['result'] ?? $response);

        return OpenSessionResponse::fromApiResponse($response);
    }

    // -------------------------------------------------------------------------
    // getTxnStatus
    // -------------------------------------------------------------------------

    /**
     * Query the status of a transaction by its paymentTokenId.
     *
     * @throws \ToanchetPay\Exceptions\HttpException  Network or HTTP error.
     * @throws ApiException                           API returned non-zero code.
     */
    public function getTransactionStatus(string $paymentTokenId): TransactionStatusResponse
    {
        $hash = $this->hashGenerator->forStatus(
            $this->config->getMerchantId(),
            $this->config->getLoginId(),
            $this->config->getPassword(),
            $paymentTokenId
        );

        $payload = [
            'loginId'        => $this->config->getLoginId(),
            'password'       => $this->config->getPassword(),
            'merchantID'     => $this->config->getMerchantId(),
            'hash'           => $hash,
            'paymentTokenid' => $paymentTokenId,
        ];

        $response = $this->http->postJson(
            $this->config->getApiUrl('getTxnStatus'),
            $payload
        );

        $this->assertApiSuccess($response);

        return TransactionStatusResponse::fromApiResponse($response);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getConfig(): ToanchetPayConfig
    {
        return $this->config;
    }

    public function getHashGenerator(): HashGenerator
    {
        return $this->hashGenerator;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $result
     * @throws ApiException
     */
    private function assertApiSuccess(array $result): void
    {
        $code = isset($result['code']) ? (int) $result['code'] : null;

        if ($code === null) {
            return; // Some endpoints wrap the code differently; outer caller handles it.
        }

        if ($code !== 0) {
            $message = (string) ($result['errorDetails'] ?? 'Unknown error');
            throw new ApiException($code, $message);
        }
    }
}
