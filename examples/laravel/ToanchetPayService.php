<?php

declare(strict_types=1);

namespace App\Services;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\TransactionStatusResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Helpers\PaymentForm;

/**
 * Laravel application-level service wrapping ToanchetPayClient.
 *
 * Inject via constructor or resolve from the container:
 *   app(ToanchetPayService::class)->initiatePayment(...)
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

    /**
     * Open a session and return the auto-submit redirect HTML.
     */
    public function initiatePayment(
        string $txid,
        float  $amount,
        string $description,
        string $successUrl,
        string $errorUrl,
        string $currency    = 'USD',
        string $expiryTime  = '5'
    ): string {
        $transaction = ToanchetPayTransaction::standard(
            txid:             $txid,
            purchaseAmount:   number_format($amount, 2, '.', ''),
            purchaseCurrency: $currency,
            purchaseDate:     now()->format('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $txid,
            expiryTime:       $expiryTime
        );

        $session = $this->client->openSession($transaction);

        return $this->form->generate($session, $transaction->toArray(), $successUrl, $errorUrl);
    }

    /**
     * Open a KHQR session and return the session (contains qrValue).
     */
    public function initiateKhqrPayment(
        string $txid,
        float  $amount,
        string $description,
        string $currency   = 'USD',
        string $expiryTime = '5'
    ): OpenSessionResponse {
        $transaction = ToanchetPayTransaction::khqr(
            txid:             $txid,
            purchaseAmount:   number_format($amount, 2, '.', ''),
            purchaseCurrency: $currency,
            purchaseDate:     now()->format('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $txid,
            expiryTime:       $expiryTime
        );

        return $this->client->openSession($transaction);
    }

    /**
     * Verify payment on the success/callback page.
     */
    public function verifyPayment(string $paymentTokenId): TransactionStatusResponse
    {
        return $this->client->getTransactionStatus($paymentTokenId);
    }
}
