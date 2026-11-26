<?php

declare(strict_types=1);

namespace ToanchetPay\DTO;

use ToanchetPay\Exceptions\ValidationException;

/** Payment notification from ACLEDA. Use getTransactionStatus() to verify the payment. */
class PaymentCallback
{
    public string $paymentTokenId;
    public int $resultCode;
    public ?string $transactionId;
    public ?string $amount;
    public ?string $paymentResult;
    public ?string $paymentType;
    public ?string $maskedCard;
    public ?string $tokenization;

    /** @param array<string,mixed> $data Decoded notification body. */
    public static function fromArray(array $data): self
    {
        $paymentTokenId = $data['_paymenttokenid'] ?? null;
        if (!is_string($paymentTokenId) || trim($paymentTokenId) === '') {
            throw new ValidationException('PaymentCallback: _paymenttokenid is required.');
        }

        $resultCode = $data['_resultCode'] ?? null;
        if (!is_int($resultCode) && !(is_string($resultCode) && preg_match('/\A-?[0-9]+\z/', $resultCode) === 1)) {
            throw new ValidationException('PaymentCallback: _resultCode must be an integer.');
        }

        $callback = new self();
        $callback->paymentTokenId = $paymentTokenId;
        $callback->resultCode = (int) $resultCode;
        $callback->transactionId = self::optionalString($data['_transactionid'] ?? null);
        $callback->amount = self::optionalString($data['_amount'] ?? null);
        $callback->paymentResult = self::optionalString($data['_paymentresult'] ?? null);
        $callback->paymentType = self::optionalString($data['_paymenttype'] ?? null);
        $callback->maskedCard = self::optionalString($data['_maskedCard'] ?? null);
        $callback->tokenization = self::optionalString($data['tokenization'] ?? null);

        return $callback;
    }

    /** @param mixed $value */
    private static function optionalString($value): ?string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }
}
