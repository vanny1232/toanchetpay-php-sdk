<?php

declare(strict_types=1);

/**
 * Plain PHP example — ToanchetPay SDK.
 *
 * Run:
 *   composer install
 *   php examples/plain-php/basic.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;
use ToanchetPay\Helpers\PaymentForm;
use ToanchetPay\Helpers\QrCodeGenerator;

// ---------------------------------------------------------------------------
// 1. Configuration
// ---------------------------------------------------------------------------

$config = new ToanchetPayConfig(
    merchantId:   'Zy8MFtBWdE67Thpurh2h4TSX7pw=',
    loginId:      'arakawauser',
    password:     'arakawauser',
    secretKey:    'YOUR_SECRET_KEY',
    merchantName: 'MERCHANTNAME',
    baseUrl:      'https://epaymentuat.acledabank.com.kh'   // UAT; change for prod
);

$client = new ToanchetPayClient($config);

// Unique transaction ID — must be unique per request in your system.
$txid = uniqid('TX', true);

// ---------------------------------------------------------------------------
// Example A: Standard hosted payment page
// ---------------------------------------------------------------------------

try {
    $transaction = ToanchetPayTransaction::standard(
        txid:             $txid,
        purchaseAmount:   '1.00',
        purchaseCurrency: 'USD',
        purchaseDate:     date('d-m-Y'),
        purchaseDesc:     'Order #12345',
        invoiceid:        $txid,
        item:             '1',
        quantity:         '1',
        expiryTime:       '5'
    );

    $session = $client->openSession($transaction);

    echo "=== Standard Session ===\n";
    echo "Session ID:       " . $session->sessionId      . "\n";
    echo "Payment Token ID: " . $session->paymentTokenId . "\n";

    // Build the redirect form
    $form = new PaymentForm($config);
    $html = $form->generate(
        session:    $session,
        txData:     $transaction->toArray(),
        successUrl: 'https://yoursite.com/payment/success',
        errorUrl:   'https://yoursite.com/payment/failed'
    );

    file_put_contents(__DIR__ . '/redirect.html', $html);
    echo "Redirect form written to: examples/plain-php/redirect.html\n\n";

} catch (ApiException $e) {
    echo "API Error [{$e->getApiCode()}]: {$e->getApiMessage()}\n";
} catch (HttpException $e) {
    echo "HTTP Error [{$e->getStatusCode()}]: {$e->getMessage()}\n";
}

// ---------------------------------------------------------------------------
// Example B: KHQR (QR code payment)
// ---------------------------------------------------------------------------

try {
    $txidQr = uniqid('QR', true);

    $transaction = ToanchetPayTransaction::khqr(
        txid:             $txidQr,
        purchaseAmount:   '5.00',
        purchaseCurrency: 'USD',
        purchaseDate:     date('d-m-Y'),
        purchaseDesc:     'KHQR Payment',
        invoiceid:        $txidQr
    );

    $session = $client->openSession($transaction);

    echo "=== KHQR Session ===\n";
    echo "Session ID:       " . $session->sessionId      . "\n";
    echo "Payment Token ID: " . $session->paymentTokenId . "\n";

    if ($session->hasQrValue()) {
        echo "QR Value:         " . substr($session->qrValue, 0, 60) . "...\n";

        // Optional: generate QR PNG (requires: composer require endroid/qr-code)
        try {
            $qr  = new QrCodeGenerator(size: 300);
            $png = $qr->toPng($session->qrValue);
            file_put_contents(__DIR__ . '/khqr.png', $png);
            echo "QR image saved to: examples/plain-php/khqr.png\n";
        } catch (\ToanchetPay\Exceptions\ToanchetPayException $e) {
            echo "(QR image skipped: {$e->getMessage()})\n";
        }
    }

    echo "\n";

} catch (ApiException $e) {
    echo "API Error [{$e->getApiCode()}]: {$e->getApiMessage()}\n";
} catch (HttpException $e) {
    echo "HTTP Error [{$e->getStatusCode()}]: {$e->getMessage()}\n";
}

// ---------------------------------------------------------------------------
// Example C: Mobile deeplink
// ---------------------------------------------------------------------------

try {
    $txidDl = uniqid('DL', true);

    $transaction = ToanchetPayTransaction::deeplink(
        txid:             $txidDl,
        purchaseAmount:   '2.50',
        purchaseCurrency: 'USD',
        purchaseDate:     date('d-m-Y'),
        purchaseDesc:     'Mobile Payment',
        invoiceid:        $txidDl,
        oprDevice:        'android',
        callBackUrl:      'appmobile://yourapp.com.kh/callback'
    );

    $session = $client->openSession($transaction);

    echo "=== Mobile Deeplink Session ===\n";
    echo "Session ID:       " . $session->sessionId      . "\n";
    echo "Payment Token ID: " . $session->paymentTokenId . "\n";

    if ($session->hasDeeplinkUrl()) {
        echo "Deeplink URL:     " . substr($session->deeplinkUrl, 0, 80) . "...\n";
    }

    echo "\n";

} catch (ApiException $e) {
    echo "API Error [{$e->getApiCode()}]: {$e->getApiMessage()}\n";
} catch (HttpException $e) {
    echo "HTTP Error [{$e->getStatusCode()}]: {$e->getMessage()}\n";
}

// ---------------------------------------------------------------------------
// Example D: Check transaction status
// ---------------------------------------------------------------------------

// In a real app you would store $session->paymentTokenId from the session above.
$storedPaymentTokenId = '2Pkx07aDMnG78wICLa7JMUghqns=';

try {
    $status = $client->getTransactionStatus($storedPaymentTokenId);

    echo "=== Transaction Status ===\n";
    echo "Success:     " . ($status->isSuccess()   ? 'Yes' : 'No')  . "\n";
    echo "Confirmed:   " . ($status->isConfirmed() ? 'Yes' : 'No')  . "\n";
    echo "Core Ref:    " . $status->coreRefNum      . "\n";
    echo "Amount:      " . $status->purchaseAmount  . " USD\n";
    echo "Tx Date:     " . $status->transactionDate . "\n";

} catch (ApiException $e) {
    echo "API Error [{$e->getApiCode()}]: {$e->getApiMessage()}\n";
} catch (HttpException $e) {
    echo "HTTP Error [{$e->getStatusCode()}]: {$e->getMessage()}\n";
}
