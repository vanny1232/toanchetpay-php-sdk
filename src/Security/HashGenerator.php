<?php

declare(strict_types=1);

namespace ToanchetPay\Security;

use ToanchetPay\Exceptions\ToanchetPayException;

class HashGenerator
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Generate the HMAC-SHA512 hash for openSessionV2.
     *
     * Message order: merchantID + loginId + password + txid
     */
    public function forSession(
        string $merchantId,
        string $loginId,
        string $password,
        string $txid
    ): string {
        return $this->generate($merchantId . $loginId . $password . $txid);
    }

    /**
     * Generate the HMAC-SHA512 hash for getTxnStatus.
     *
     * Message order: merchantID + loginId + password + paymentTokenId
     */
    public function forStatus(
        string $merchantId,
        string $loginId,
        string $password,
        string $paymentTokenId
    ): string {
        return $this->generate($merchantId . $loginId . $password . $paymentTokenId);
    }

    /**
     * Generate HMAC-SHA512 hash for an arbitrary message string.
     * Returns uppercase hexadecimal — no spaces, no separators.
     */
    public function generate(string $message): string
    {
        $raw = hash_hmac('sha512', $message, $this->secretKey, true);

        if ($raw === false) {
            throw new ToanchetPayException('Failed to generate HMAC-SHA512 hash. Verify ext-hash is installed.');
        }

        return strtoupper(bin2hex($raw));
    }
}
