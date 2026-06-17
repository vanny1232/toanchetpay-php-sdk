<?php

declare(strict_types=1);

namespace App\Service;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\TransactionStatusResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Helpers\PaymentForm;

/**
 * Symfony service — wire via services.yaml or autowiring.
 *
 * services.yaml:
 *
 *   ToanchetPay\Config\ToanchetPayConfig:
 *     arguments:
 *       $merchantId:   '%env(TOANCHETPAY_MERCHANT_ID)%'
 *       $loginId:      '%env(TOANCHETPAY_LOGIN_ID)%'
 *       $password:     '%env(TOANCHETPAY_PASSWORD)%'
 *       $secretKey:    '%env(TOANCHETPAY_SECRET_KEY)%'
 *       $merchantName: '%env(TOANCHETPAY_MERCHANT_NAME)%'
 *       $baseUrl:      '%env(TOANCHETPAY_BASE_URL)%'
 *
 *   ToanchetPay\Client\ToanchetPayClient:
 *     autowire: true
 *
 *   ToanchetPay\Helpers\PaymentForm:
 *     autowire: true
 *
 *   App\Service\ToanchetPayService:
 *     autowire: true
 */
class ToanchetPayService
{
    private ToanchetPayClient  $client;
    private ToanchetPayConfig  $config;
    private PaymentForm $form;

    public function __construct(ToanchetPayClient $client, ToanchetPayConfig $config, PaymentForm $form)
    {
        $this->client = $client;
        $this->config = $config;
        $this->form   = $form;
    }

    public function createSession(
        string $txid,
        string $amount,
        string $currency,
        string $description,
        string $invoiceId,
        string $expiryTime = '5'
    ): OpenSessionResponse {
        $transaction = ToanchetPayTransaction::standard(
            txid:             $txid,
            purchaseAmount:   $amount,
            purchaseCurrency: $currency,
            purchaseDate:     (new \DateTimeImmutable())->format('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $invoiceId,
            expiryTime:       $expiryTime
        );

        return $this->client->openSession($transaction);
    }

    public function createKhqrSession(
        string $txid,
        string $amount,
        string $currency,
        string $description,
        string $invoiceId
    ): OpenSessionResponse {
        $transaction = ToanchetPayTransaction::khqr(
            txid:             $txid,
            purchaseAmount:   $amount,
            purchaseCurrency: $currency,
            purchaseDate:     (new \DateTimeImmutable())->format('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $invoiceId
        );

        return $this->client->openSession($transaction);
    }

    public function getPaymentForm(
        OpenSessionResponse $session,
        array $txData,
        string $successUrl,
        string $errorUrl
    ): string {
        return $this->form->generate($session, $txData, $successUrl, $errorUrl);
    }

    public function checkStatus(string $paymentTokenId): TransactionStatusResponse
    {
        return $this->client->getTransactionStatus($paymentTokenId);
    }
}
