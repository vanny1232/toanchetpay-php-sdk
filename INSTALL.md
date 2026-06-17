# ToanchetPay PHP SDK — Installation & Usage Guide

This guide walks you through installing the SDK, configuring your credentials, and making your first payment request.

---

## 1. Requirements

Before you begin, make sure your environment meets these requirements:

| Item | Requirement |
|------|-------------|
| PHP | 7.4, 8.0, 8.1, 8.2, or 8.3 |
| PHP extensions | `curl`, `hash`, `json` (usually enabled by default) |
| Composer | Any recent version ([getcomposer.org](https://getcomposer.org)) |

Check your PHP version and extensions:

```bash
php -v
php -m | grep -E 'curl|hash|json'
```

---

## 2. Install via Composer

Run this command in your project's root directory:

```bash
composer require toanchet/toanchetpay-sdk
```

### Optional packages

```bash
# Use Guzzle instead of cURL as the HTTP transport
composer require guzzlehttp/guzzle:^7.0

# Generate QR code images from KHQR qrValue
composer require endroid/qr-code:^4.0
```

---

## 3. Configure Your Credentials

You need five values from your ToanchetPay / ACLEDA merchant account:

| Variable | Description |
|----------|-------------|
| `MERCHANT_ID` | Merchant ID (Base64 string) |
| `LOGIN_ID` | API login ID |
| `PASSWORD` | API password |
| `SECRET_KEY` | HMAC signing key (never sent over the network) |
| `MERCHANT_NAME` | Merchant name as it appears in the payment URL |

### Option A — Environment variables (recommended)

Add to your `.env` file:

```dotenv
TOANCHETPAY_MERCHANT_ID=Zy8MFtBWdE67Thpurh2h4TSX7pw=
TOANCHETPAY_LOGIN_ID=arakawauser
TOANCHETPAY_PASSWORD=arakawauser
TOANCHETPAY_SECRET_KEY=your_secret_key_here
TOANCHETPAY_MERCHANT_NAME=MERCHANTNAME
TOANCHETPAY_BASE_URL=https://epaymentuat.acledabank.com.kh
```

> Use `https://epaymentuat.acledabank.com.kh` for **UAT/testing** and the live URL for **production**.

### Option B — Inline PHP

```php
use ToanchetPay\Config\ToanchetPayConfig;

$config = new ToanchetPayConfig(
    'Zy8MFtBWdE67Thpurh2h4TSX7pw=',       // merchantId
    'arakawauser',                          // loginId
    'arakawauser',                          // password
    'your_secret_key_here',                 // secretKey
    'MERCHANTNAME',                         // merchantName
    'https://epaymentuat.acledabank.com.kh' // baseUrl (UAT)
);
```

### Option C — From array / config file

```php
$config = ToanchetPayConfig::fromArray([
    'merchant_id'   => getenv('TOANCHETPAY_MERCHANT_ID'),
    'login_id'      => getenv('TOANCHETPAY_LOGIN_ID'),
    'password'      => getenv('TOANCHETPAY_PASSWORD'),
    'secret_key'    => getenv('TOANCHETPAY_SECRET_KEY'),
    'merchant_name' => getenv('TOANCHETPAY_MERCHANT_NAME'),
    'base_url'      => getenv('TOANCHETPAY_BASE_URL'),
]);
```

---

## 4. Your First Payment (Standard Flow)

Below is a minimal working example using plain PHP. The same pattern applies in any framework.

### Step 1 — Create the client

```php
<?php
require 'vendor/autoload.php';

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Helpers\PaymentForm;

$config = new ToanchetPayConfig(
    getenv('TOANCHETPAY_MERCHANT_ID'),
    getenv('TOANCHETPAY_LOGIN_ID'),
    getenv('TOANCHETPAY_PASSWORD'),
    getenv('TOANCHETPAY_SECRET_KEY'),
    getenv('TOANCHETPAY_MERCHANT_NAME'),
    getenv('TOANCHETPAY_BASE_URL')
);

$client = new ToanchetPayClient($config);
```

### Step 2 — Open a payment session

```php
$transaction = ToanchetPayTransaction::standard(
    uniqid('TX'),     // unique transaction ID
    '10.00',          // amount
    'USD',            // currency
    date('d-m-Y'),    // date  (DD-MM-YYYY)
    'Order #1234',    // description
    'INV-1234'        // invoice ID
);

$session = $client->openSession($transaction);

echo $session->sessionId;       // H0x3/fgAGuXOtKn3cksDfZZQ4YM=
echo $session->paymentTokenId;  // 2Pkx07aDMnG78wICLa7JMUghqns=
```

### Step 3 — Redirect the user to the payment page

```php
$form = new PaymentForm($config);
$form->render(
    $session,
    $transaction->toArray(),
    'https://yoursite.com/payment/success',  // success URL
    'https://yoursite.com/payment/failed'    // failure URL
);
// render() outputs an auto-submit HTML form and exits
```

### Step 4 — Verify the payment on your callback page

```php
// On your success/failure page:
$paymentTokenId = $_POST['paymentTokenId'] ?? '';

$status = $client->getTransactionStatus($paymentTokenId);

if ($status->isSuccess() && $status->isConfirmed()) {
    echo "Payment confirmed!";
    echo "Core reference: " . $status->coreRefNum;
    echo "Amount: " . $status->purchaseAmount . " " . $status->purchaseCurrency;
} else {
    echo "Payment not confirmed.";
}
```

---

## 5. Payment Types — Quick Reference

| Type | Factory method | Notes |
|------|---------------|-------|
| Standard (ACLEDA hosted) | `ToanchetPayTransaction::standard(...)` | Redirects user to ACLEDA payment page |
| MPGS (Mastercard) | `ToanchetPayTransaction::mpgs(...)` | Card payment via Mastercard gateway |
| KHQR (web) | `ToanchetPayTransaction::khqr(...)` | Returns `qrValue` to display as QR |
| KHQR POS / Kiosk | `ToanchetPayTransaction::khqrPos(..., $terminalId, $counterId)` | For terminal/kiosk displays |
| Mobile Deeplink | `ToanchetPayTransaction::deeplink(..., $oprDevice, $callBackUrl)` | Opens ACLEDA mobile app |

All types share the same `openSession()` call — only the factory method differs.

---

## 6. Error Handling

Wrap calls in a try/catch to handle the three exception types:

```php
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;
use ToanchetPay\Exceptions\ValidationException;

try {
    $session = $client->openSession($transaction);
} catch (ValidationException $e) {
    // Bad input — fix your parameters before retrying
    echo "Validation error: " . $e->getMessage();
} catch (ApiException $e) {
    // The gateway returned an error code
    echo "API error " . $e->getApiCode() . ": " . $e->getApiMessage();
} catch (HttpException $e) {
    // Network / connection problem
    echo "HTTP " . $e->getStatusCode() . ": " . $e->getMessage();
}
```

---

## 7. Framework Integration

### Laravel

```bash
php artisan vendor:publish --tag=toanchetpay-config
```

Add credentials to `.env`, register `ToanchetPayServiceProvider` in `config/app.php`, then inject `ToanchetPayClient` via the constructor. See [examples/laravel/](examples/laravel/) for the full provider and controller.

### Symfony

Configure `ToanchetPayConfig` and `ToanchetPayClient` in `services.yaml` using `%env(...)%` bindings. See [examples/symfony/](examples/symfony/).

### CodeIgniter 4

Copy [examples/codeigniter/ToanchetPay.php](examples/codeigniter/ToanchetPay.php) to `app/Libraries/` and use it as a library wrapper.

### Plain PHP

See [examples/plain-php/basic.php](examples/plain-php/basic.php) for a self-contained example.

---

## 8. Verify the Installation

Run the included test suite to confirm everything is working:

```bash
composer install
php vendor/bin/phpunit --no-coverage
```

Expected output:

```
OK (30 tests, 56 assertions)
```

---

## 9. Security Checklist

- [ ] Never commit your `SECRET_KEY` to version control — use environment variables.
- [ ] Set `verifySsl: true` (the default) in production.
- [ ] Generate a new unique `txid` for every transaction.
- [ ] Always verify `isSuccess() && isConfirmed()` before fulfilling an order.

---

## Need Help?

- Full API reference: [README.md](README.md)
- Examples: [examples/](examples/)
- Issues: [github.com/acleda/toanchetpay-php-sdk/issues](https://github.com/acleda/toanchetpay-php-sdk/issues)
