# ToanchetPay PHP SDK

A PHP SDK for ACLEDA Toanchet Pay. It supports hosted Account/Wallet and MPGS payments, KHQR webview and raw KHQR strings, mobile deeplinks, tokenization, status checks, callbacks, and refunds. [ACLEDA API integration guide](https://toanchetpay.acledabank.com.kh/toanchetpay/api-integration)

## Requirements

| Requirement | Version |
|---|---|
| PHP | 7.4, 8.0, 8.1, 8.2, 8.3 |
| ext-curl | any |
| ext-hash | any |
| ext-json | any |

No framework dependencies. Works with Laravel, Symfony, CodeIgniter, Yii, Slim, Lumen, and plain PHP.

## Installation

```bash
composer require toanchet/toanchetpay-sdk
```

Optional packages:

```bash
# Guzzle HTTP adapter instead of cURL
composer require guzzlehttp/guzzle:^7.0

# QR code image generation from KHQR qrValue
composer require endroid/qr-code:^4.0
```

---

## Quick Start

```php
use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Helpers\PaymentForm;

// 1. Configure
$config = new ToanchetPayConfig(
    'Zy8MFtBWdE67Thpurh2h4TSX7pw=',   // merchantId
    'arakawauser',                      // loginId
    'arakawauser',                      // password
    'YOUR_SECRET_KEY',                  // secretKey
    'MERCHANTNAME',                     // merchantName (in the URL)
    'https://epaymentuat.acledabank.com.kh'  // baseUrl (UAT)
);

$client = new ToanchetPayClient($config);

// 2. Open a session
$transaction = ToanchetPayTransaction::standard(
    uniqid('TX'),  // txid
    '10.00',       // purchaseAmount
    'USD',         // purchaseCurrency
    date('d-m-Y'), // purchaseDate  DD-MM-YYYY
    'Order #1234', // purchaseDesc
    'INV-1234'     // invoiceid
);

$session = $client->openSession($transaction);

// Store $session->sessionId and $session->paymentTokenId with the order.

// 3. Redirect user to the payment page
$form = new PaymentForm($config);
$form->render($session, $transaction->toArray(), 'https://yoursite.com/success', 'https://yoursite.com/failed');

// 4. In your return handler, load the order and use its saved payment token.
$status = $client->getTransactionStatus($storedPaymentTokenId);

if ($status->isSuccess() && $status->isConfirmed()) {
    echo "Payment confirmed! Core ref: " . $status->coreRefNum;
}
```

---

## Configuration

### Constructor

```php
new ToanchetPayConfig(
    string $merchantId,
    string $loginId,
    string $password,
    string $secretKey,
    string $merchantName,
    string $baseUrl   = 'https://epaymentuat.acledabank.com.kh',
    int    $timeout   = 30,
    bool   $verifySsl = true,
    ?string $refundBaseUrl = null
)
```

### From array (config files)

```php
$config = ToanchetPayConfig::fromArray([
    'merchant_id'   => env('TOANCHETPAY_MERCHANT_ID'),
    'login_id'      => env('TOANCHETPAY_LOGIN_ID'),
    'password'      => env('TOANCHETPAY_PASSWORD'),
    'secret_key'    => env('TOANCHETPAY_SECRET_KEY'),
    'merchant_name' => env('TOANCHETPAY_MERCHANT_NAME'),
    'base_url'      => env('TOANCHETPAY_BASE_URL', 'https://epaymentuat.acledabank.com.kh'),
    'refund_base_url' => env('TOANCHETPAY_REFUND_BASE_URL'),
]);
```

### Environment variables

```dotenv
TOANCHETPAY_MERCHANT_ID=Zy8MFtBWdE67Thpurh2h4TSX7pw=
TOANCHETPAY_LOGIN_ID=arakawauser
TOANCHETPAY_PASSWORD=arakawauser
TOANCHETPAY_SECRET_KEY=your_secret_key
TOANCHETPAY_MERCHANT_NAME=MERCHANTNAME
TOANCHETPAY_BASE_URL=https://epaymentuat.acledabank.com.kh
```

---

## Payment Types

### 1. Standard (ACLEDA hosted page)

```php
$tx = ToanchetPayTransaction::standard(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId
);
$session = $client->openSession($tx);
// Redirect user to payment page (see PaymentForm section)
```

### 2. MPGS — Mastercard Payment Gateway

```php
$tx = ToanchetPayTransaction::mpgs(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId
);
// Adds paymentCard=1 automatically
$session = $client->openSession($tx);
```

### 3. KHQR (web — hosted page)

```php
$tx = ToanchetPayTransaction::khqr(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId
);
$session = $client->openSession($tx);
$form = new PaymentForm($config);
$form->render($session, $tx->toArray(), $successUrl, $errorUrl);
```

### 4. KHQR String and POS

```php
$tx = ToanchetPayTransaction::khqrString(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId
);
$session = $client->openSession($tx);
// Use $session->qrValue to generate a QR image.
```

Pass terminal and counter IDs when ACLEDA assigns them to your POS or kiosk.

```php
$tx = ToanchetPayTransaction::khqrPos(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId,
    'TERMINAL_001',  // terminalId
    'Counter 1'      // counterId
);
$session = $client->openSession($tx);
echo $session->qrValue; // display on terminal screen
```

### 5. Mobile Deeplink (ACLEDA mobile app)

```php
$tx = ToanchetPayTransaction::deeplink(
    $txid, $amount, 'USD', date('d-m-Y'), 'Description', $invoiceId,
    'android',                             // oprDevice: 'android' or 'ios'
    'appmobile://yourapp.com.kh/callback'  // callBackUrl
);
$session = $client->openSession($tx);

if ($session->hasDeeplinkUrl()) {
    // Redirect or pass to mobile app
    header('Location: ' . $session->deeplinkUrl);
}
```

---

## PaymentForm

Generates an auto-submit HTML form that POSTs the user to `paymentPage.jsp`.

```php
$form = new PaymentForm($config);

// Option A: get HTML string
$html = $form->generate(
    $session,
    $transaction->toArray(),
    'https://yoursite.com/payment/success',
    'https://yoursite.com/payment/failed'
);

// Option B: output and redirect immediately
$form->render($session, $transaction->toArray(), $successUrl, $errorUrl);
```

---

## KHQR Image Generation

Requires `endroid/qr-code`:

```bash
composer require endroid/qr-code:^4.0
```

```php
use ToanchetPay\Helpers\QrCodeGenerator;

$qr = new QrCodeGenerator(300, 10); // size=300px, margin=10px

// Save PNG to disk
file_put_contents('qr.png', $qr->toPng($session->qrValue));

// Embed in HTML
echo '<img src="' . $qr->toBase64DataUri($session->qrValue) . '">';
```

---

## Transaction Status

Call after the user returns from the payment page or after your server receives a payment notification. Use the payment token saved with the order.

```php
$status = $client->getTransactionStatus($paymentTokenId);

$status->isSuccess();       // bool — code === 0
$status->isConfirmed();     // bool — confirmDate > 0

$status->coreRefNum;        // "FT221190F2J9PNL2"
$status->purchaseAmount;    // 1.0 (float)
$status->transactionDate;   // "2024-01-01 10:00:00"
$status->txid;
$status->invoiceId;
$status->paymentTokenId;
```

### Payment callbacks

Register your notification URL with ACLEDA. Parse the notification, find the order by its saved payment token, and verify the payment through `getTransactionStatus()`. Match the verified transaction ID and amount with the order before recording payment. [ACLEDA callback documentation](https://toanchetpay.acledabank.com.kh/toanchetpay/callbackurl)

```php
use ToanchetPay\DTO\PaymentCallback;

$data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$callback = PaymentCallback::fromArray($data);
// Look up the order using $callback->paymentTokenId.
$status = $client->getTransactionStatus($storedPaymentTokenId);
```

## Tokenization

Use the signature and secret code specified by ACLEDA for your merchant's tokenization integration. [ACLEDA tokenization guide](https://toanchetpay.acledabank.com.kh/toanchetpay/tokenize-integration)

```php
// Register a payment method on ACLEDA's hosted page.
$session = $client->openTokenizationSession($signature);
echo $form->generateTokenization($session, $successUrl, $errorUrl);
```

The tokenization notification can carry the saved token in `PaymentCallback::$tokenization`.

```php
// Charge a saved token.
$payment = $client->payTokenize(
    $tokenId, $txid, $invoiceId, '5.00', 'USD', 'Order payment', $secretCode
);
$paymentTokenId = $payment['paymentTokenId'];

// Delete a saved token using the session from a new MPGS request (paymentCard=1).
$mpgsSession = $client->openSession($mpgsTransaction);
$client->deleteTokenization($tokenId, $mpgsSession, $signature);
```

## Refunds

Use the session ID and payment token ID from the original paid KHQR, deeplink, or MPGS session. The refund currency must match the payment currency. Partial refunds are supported up to the remaining refundable amount. [ACLEDA refund guide](https://toanchetpay.acledabank.com.kh/toanchetpay/refund)

```php
$originalSession = \ToanchetPay\DTO\OpenSessionResponse::fromApiResponse([
    'sessionid' => $storedSessionId,
    'xTran' => ['paymentTokenid' => $storedPaymentTokenId],
]);
$result = $client->refundTransaction($originalSession, '0.50', 'USD', 'Cancelled item');
```

The default UAT refund endpoint uses port `8443`. Set `refundBaseUrl` or `refund_base_url` when ACLEDA gives you a different refund host. `refundTransaction()` signs the payment token using HMAC-SHA512 and throws `ApiException` for bank errors such as code `334845`.

---

## Custom HTTP Client

### Guzzle adapter

```php
use ToanchetPay\Http\GuzzleHttpClient;
use GuzzleHttp\Client;

$http   = new GuzzleHttpClient(new Client(['timeout' => 30]));
$client = new ToanchetPayClient($config, $http);
```

### Custom adapter

Implement `ToanchetPay\Http\HttpClientInterface`:

```php
use ToanchetPay\Http\HttpClientInterface;

class MyHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $payload): array
    {
        // your implementation
    }
}

$client = new ToanchetPayClient($config, new MyHttpClient());
```

---

## Hash Generation (standalone)

```php
use ToanchetPay\Security\HashGenerator;

$gen = new HashGenerator('your_secret_key');

// For openSessionV2: merchantID + loginId + password + txid
$hash = $gen->forSession($merchantId, $loginId, $password, $txid);

// For getTxnStatus: merchantID + loginId + password + paymentTokenId
$hash = $gen->forStatus($merchantId, $loginId, $password, $paymentTokenId);

// Arbitrary message
$hash = $gen->generate('any string');
// → uppercase HEX, 128 characters (HMAC-SHA512)
```

---

## Framework Integration

### Laravel

1. Publish config:
   ```bash
   php artisan vendor:publish --tag=toanchetpay-config
   ```

2. Add to `.env`:
   ```
   TOANCHETPAY_MERCHANT_ID=...
   TOANCHETPAY_LOGIN_ID=...
   TOANCHETPAY_PASSWORD=...
   TOANCHETPAY_SECRET_KEY=...
   TOANCHETPAY_MERCHANT_NAME=MERCHANTNAME
   ```

3. Register `ToanchetPayServiceProvider` in `config/app.php` (see [examples/laravel/](examples/laravel/)).

4. Inject via constructor:
   ```php
   class PaymentController extends Controller
   {
       public function __construct(private ToanchetPayClient $toanchetpay) {}
   }
   ```

### Symfony

Configure in `services.yaml` (see [examples/symfony/](examples/symfony/)):

```yaml
ToanchetPay\Config\ToanchetPayConfig:
    arguments:
        $merchantId:   '%env(TOANCHETPAY_MERCHANT_ID)%'
        $loginId:      '%env(TOANCHETPAY_LOGIN_ID)%'
        $password:     '%env(TOANCHETPAY_PASSWORD)%'
        $secretKey:    '%env(TOANCHETPAY_SECRET_KEY)%'
        $merchantName: '%env(TOANCHETPAY_MERCHANT_NAME)%'
        $baseUrl:      '%env(TOANCHETPAY_BASE_URL)%'

ToanchetPay\Client\ToanchetPayClient:
    autowire: true

ToanchetPay\Helpers\PaymentForm:
    autowire: true
```

### CodeIgniter 4

Copy `examples/codeigniter/ToanchetPay.php` to `app/Libraries/ToanchetPay.php`.

```php
$toanchetpay    = new \App\Libraries\ToanchetPay();
$session = $toanchetpay->openSession('TX001', '5.00', 'USD', 'Order', 'INV001');
$html    = $toanchetpay->getPaymentForm($session, [...], $successUrl, $errorUrl);
```

---

## Error Handling

```php
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;
use ToanchetPay\Exceptions\ValidationException;

try {
    $session = $client->openSession($transaction);
} catch (ValidationException $e) {
    // Missing or invalid parameters — fix before retrying
    echo $e->getMessage();
} catch (ApiException $e) {
    // ACLEDA returned a non-zero code
    echo "Code: "    . $e->getApiCode();
    echo "Message: " . $e->getApiMessage();
} catch (HttpException $e) {
    // Network error, timeout, or non-2xx HTTP response
    echo "HTTP " . $e->getStatusCode() . ": " . $e->getMessage();
}
```

| Exception | Cause |
|---|---|
| `ValidationException` | Missing required input or invalid callback fields |
| `ApiException` | ACLEDA API returned `code != 0` |
| `HttpException` | Network failure, non-2xx response, or malformed gateway result |

---

## Project Structure

```
src/
├── Client/
│   └── ToanchetPayClient.php          ← Main entry point
├── Config/
│   └── ToanchetPayConfig.php          ← Credentials & URL config
├── Security/
│   └── HashGenerator.php       ← HMAC-SHA512 (uppercase HEX)
├── Http/
│   ├── HttpClientInterface.php
│   ├── CurlHttpClient.php      ← Default (no dependencies)
│   └── GuzzleHttpClient.php    ← Optional adapter
├── DTO/
│   ├── ToanchetPayTransaction.php     ← Request builder (all payment types)
│   ├── OpenSessionResponse.php ← Typed response from openSessionV2
│   ├── PaymentCallback.php    ← Merchant payment notification
│   └── TransactionStatusResponse.php
├── Exceptions/
│   ├── ToanchetPayException.php
│   ├── ApiException.php
│   ├── HttpException.php
│   └── ValidationException.php
└── Helpers/
    ├── PaymentForm.php         ← Auto-submit HTML form generator
    └── QrCodeGenerator.php     ← PNG/base64 from KHQR qrValue

examples/
├── plain-php/basic.php
├── laravel/{ToanchetPayServiceProvider,ToanchetPayService,PaymentController}.php
├── symfony/ToanchetPayService.php
└── codeigniter/ToanchetPay.php

tests/
└── Unit/
    ├── HashGeneratorTest.php
    ├── PaymentCallbackTest.php
    ├── PaymentFormTest.php
    ├── ToanchetPayClientTest.php
    └── ToanchetPayTransactionTest.php
```

---

## Running Tests

```bash
composer install
php vendor/bin/phpunit --no-coverage
```

---

## Security Notes

- The **secret key** is never sent over the network — only the HMAC hash derived from it.
- The SDK signs `openSessionV2` requests with the transaction ID. Status and refund requests use the payment token ID.
- Always set `verifySsl: true` in production.
- The `PaymentForm` helper escapes all values with `htmlspecialchars` to prevent XSS.

---

## License

MIT
