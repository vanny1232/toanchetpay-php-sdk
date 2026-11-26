<?php

declare(strict_types=1);

namespace ToanchetPay\Client;

use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\TransactionStatusResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;
use ToanchetPay\Exceptions\ValidationException;
use ToanchetPay\Http\CurlHttpClient;
use ToanchetPay\Http\HttpClientInterface;
use ToanchetPay\Security\HashGenerator;

/**
 * Main entry point for the ToanchetPay SDK.
 *
 * Quick start:
 *
 *   $config = new ToanchetPayConfig(
 *       'MERCHANT_ID', 'LOGIN_ID', 'PASSWORD', 'SECRET_KEY', 'MERCHANTNAME'
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

        $response = $this->http->postJson(
            $this->config->getApiUrl('openSessionV2'),
            $this->credentials() + [
                'hash' => $hash,
                'xpayTransaction' => $transaction->toArray(),
            ]
        );

        return $this->sessionFromResponse($response);
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
        $response = $this->http->postJson(
            $this->config->getApiUrl('getTxnStatus'),
            $this->credentials() + [
                'hash' => $this->hashForPaymentToken($paymentTokenId),
                'paymentTokenid' => $paymentTokenId,
            ]
        );

        $this->assertApiSuccess($this->apiResult($response));

        return TransactionStatusResponse::fromApiResponse($response);
    }

    /** Open an operationType=2 session using the merchant's tokenization signature. */
    public function openTokenizationSession(string $signature): OpenSessionResponse
    {
        $response = $this->http->postJson(
            $this->config->getApiUrl('openSessionV2'),
            $this->credentials() + [
                'signature' => $signature,
                'xpayTransaction' => ['operationType' => 2],
            ]
        );

        return $this->sessionFromResponse($response);
    }

    /**
     * Charge a saved token with the merchant's tokenization secret code.
     *
     * @return array<string,mixed> Payment details including payReference and paymentTokenId.
     */
    public function payTokenize(
        string $tokenId,
        string $transactionId,
        string $invoiceId,
        string $amount,
        string $currency,
        string $description,
        string $secretCode
    ): array {
        $response = $this->http->postJson(
            $this->config->getApiUrl('payTokenize'),
            $this->credentials('merchantId') + [
                'secretCode' => $secretCode,
                'tokenId' => $tokenId,
                'transactionId' => $transactionId,
                'invoiceId' => $invoiceId,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
            ]
        );

        $result = $this->apiResult($response);
        $this->assertApiSuccess($this->apiResult($result));

        return $result;
    }

    /**
     * Delete a saved token using session IDs from openSession() with paymentCard=1.
     *
     * @return array<string,mixed> API result including tokenStatus.
     */
    public function deleteTokenization(string $tokenId, OpenSessionResponse $session, string $signature): array
    {
        $this->assertSessionIds($session);

        $response = $this->http->postJson(
            $this->config->getApiUrl('deleteTokenization'),
            $this->credentials() + [
                'sessionId' => $session->sessionId,
                'signature' => $signature,
                'token' => $tokenId,
                'paymentTokenId' => $session->paymentTokenId,
            ]
        );

        $result = $this->apiResult($response);
        $this->assertApiSuccess($result);

        return $result;
    }

    /**
     * Refund all or part of a paid KHQR, deeplink, or MPGS transaction.
     *
     * @return array<string,mixed> API result including code, message and status.
     */
    public function refundTransaction(OpenSessionResponse $session, string $amount, string $currency, string $reason): array
    {
        $this->assertSessionIds($session);

        $response = $this->http->postJson(
            $this->config->getRefundApiUrl(),
            $this->credentials('merchantId') + [
                'hash' => $this->hashForPaymentToken($session->paymentTokenId),
                'paymentTokenId' => $session->paymentTokenId,
                'sessionId' => $session->sessionId,
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
            ]
        );

        $result = $this->apiResult($response);
        $this->assertApiSuccess($result);

        return $result;
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

    /** @return array<string,string> */
    private function credentials(string $merchantIdField = 'merchantID'): array
    {
        return [
            'loginId' => $this->config->getLoginId(),
            'password' => $this->config->getPassword(),
            $merchantIdField => $this->config->getMerchantId(),
        ];
    }

    private function hashForPaymentToken(string $paymentTokenId): string
    {
        return $this->hashGenerator->forStatus(
            $this->config->getMerchantId(),
            $this->config->getLoginId(),
            $this->config->getPassword(),
            $paymentTokenId
        );
    }

    private function sessionFromResponse(array $response): OpenSessionResponse
    {
        $this->assertApiSuccess($this->apiResult($response));
        $session = OpenSessionResponse::fromApiResponse($response);
        if ($session->sessionId === '' || $session->paymentTokenId === '') {
            throw new HttpException('ACLEDA session response is missing sessionid or paymentTokenid.');
        }

        return $session;
    }

    private function assertSessionIds(OpenSessionResponse $session): void
    {
        if (trim($session->sessionId ?? '') === '' || trim($session->paymentTokenId ?? '') === '') {
            throw new ValidationException('The original session ID and payment token ID are required.');
        }
    }

    /** @return array<string,mixed> */
    private function apiResult(array $response): array
    {
        $result = $response['result'] ?? $response;
        if (!is_array($result)) {
            throw new HttpException('ACLEDA returned an invalid API result.');
        }

        return $result;
    }

    /** @param array<string,mixed> $result */
    private function assertApiSuccess(array $result): void
    {
        $code = $result['code'] ?? null;
        if (!is_int($code) && !(is_string($code) && preg_match('/\A-?[0-9]+\z/', $code) === 1)) {
            throw new HttpException('ACLEDA response is missing a valid result code.');
        }

        if ((int) $code !== 0) {
            $message = (string) ($result['errorDetails'] ?? $result['message'] ?? 'Unknown error');
            throw new ApiException((int) $code, $message);
        }
    }
}
