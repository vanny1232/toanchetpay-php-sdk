<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 Library — ToanchetPay.
 *
 * Place this file in app/Libraries/ToanchetPay.php
 *
 * Config via .env (CodeIgniter 4 format):
 *   TOANCHETPAY_MERCHANT_ID   = Zy8MFtBWdE67Thpurh2h4TSX7pw=
 *   TOANCHETPAY_LOGIN_ID      = arakawauser
 *   TOANCHETPAY_PASSWORD      = arakawauser
 *   TOANCHETPAY_SECRET_KEY    = YOUR_SECRET_KEY
 *   TOANCHETPAY_MERCHANT_NAME = MERCHANTNAME
 *   TOANCHETPAY_BASE_URL      = https://epaymentuat.acledabank.com.kh
 *
 * Usage in a controller:
 *   $toanchetpay    = new \App\Libraries\ToanchetPay();
 *   $session = $toanchetpay->openSession('TX001', '5.00', 'USD', 'Order #1', 'TX001');
 *   $html    = $toanchetpay->getPaymentForm($session, [...], $successUrl, $errorUrl);
 */

namespace App\Libraries;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\TransactionStatusResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Helpers\PaymentForm;

class ToanchetPay
{
    private ToanchetPayClient  $client;
    private ToanchetPayConfig  $config;
    private PaymentForm $form;

    public function __construct()
    {
        $this->config = new ToanchetPayConfig(
            merchantId:   getenv('TOANCHETPAY_MERCHANT_ID')   ?: env('TOANCHETPAY_MERCHANT_ID',   ''),
            loginId:      getenv('TOANCHETPAY_LOGIN_ID')       ?: env('TOANCHETPAY_LOGIN_ID',       ''),
            password:     getenv('TOANCHETPAY_PASSWORD')       ?: env('TOANCHETPAY_PASSWORD',       ''),
            secretKey:    getenv('TOANCHETPAY_SECRET_KEY')     ?: env('TOANCHETPAY_SECRET_KEY',     ''),
            merchantName: getenv('TOANCHETPAY_MERCHANT_NAME')  ?: env('TOANCHETPAY_MERCHANT_NAME',  ''),
            baseUrl:      getenv('TOANCHETPAY_BASE_URL')       ?: env('TOANCHETPAY_BASE_URL', 'https://epaymentuat.acledabank.com.kh')
        );

        $this->client = new ToanchetPayClient($this->config);
        $this->form   = new PaymentForm($this->config);
    }

    /**
     * Open a standard payment session.
     */
    public function openSession(
        string $txid,
        string $amount,
        string $currency,
        string $description,
        string $invoiceId,
        string $expiryTime = '5'
    ): OpenSessionResponse {
        $tx = ToanchetPayTransaction::standard(
            txid:             $txid,
            purchaseAmount:   $amount,
            purchaseCurrency: $currency,
            purchaseDate:     date('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $invoiceId,
            expiryTime:       $expiryTime
        );

        return $this->client->openSession($tx);
    }

    /**
     * Open a KHQR session (response contains qrValue).
     */
    public function openKhqrSession(
        string $txid,
        string $amount,
        string $currency,
        string $description,
        string $invoiceId
    ): OpenSessionResponse {
        $tx = ToanchetPayTransaction::khqr(
            txid:             $txid,
            purchaseAmount:   $amount,
            purchaseCurrency: $currency,
            purchaseDate:     date('d-m-Y'),
            purchaseDesc:     $description,
            invoiceid:        $invoiceId
        );

        return $this->client->openSession($tx);
    }

    /**
     * Build the auto-submit HTML payment form.
     *
     * @param array<string,mixed> $txData
     */
    public function getPaymentForm(
        OpenSessionResponse $session,
        array $txData,
        string $successUrl,
        string $errorUrl
    ): string {
        return $this->form->generate($session, $txData, $successUrl, $errorUrl);
    }

    /**
     * Check the status of a transaction.
     */
    public function checkStatus(string $paymentTokenId): TransactionStatusResponse
    {
        return $this->client->getTransactionStatus($paymentTokenId);
    }
}
