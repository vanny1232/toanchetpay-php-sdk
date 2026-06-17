<?php

declare(strict_types=1);

namespace ToanchetPay\Config;

use ToanchetPay\Exceptions\ValidationException;

class ToanchetPayConfig
{
    private string $merchantId;
    private string $loginId;
    private string $password;
    private string $secretKey;
    private string $merchantName;
    private string $baseUrl;
    private int $timeout;
    private bool $verifySsl;

    public function __construct(
        string $merchantId,
        string $loginId,
        string $password,
        string $secretKey,
        string $merchantName,
        string $baseUrl = 'https://epaymentuat.acledabank.com.kh',
        int $timeout = 30,
        bool $verifySsl = true
    ) {
        $this->validate($merchantId, $loginId, $password, $secretKey, $merchantName);

        $this->merchantId   = $merchantId;
        $this->loginId      = $loginId;
        $this->password     = $password;
        $this->secretKey    = $secretKey;
        $this->merchantName = $merchantName;
        $this->baseUrl      = rtrim($baseUrl, '/');
        $this->timeout      = $timeout;
        $this->verifySsl    = $verifySsl;
    }

    /**
     * Create from associative array — convenient for config files.
     *
     * @param array<string,mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['merchant_id']   ?? $config['merchantId']   ?? '',
            $config['login_id']      ?? $config['loginId']      ?? '',
            $config['password']      ?? '',
            $config['secret_key']    ?? $config['secretKey']    ?? '',
            $config['merchant_name'] ?? $config['merchantName'] ?? '',
            $config['base_url']      ?? $config['baseUrl']      ?? 'https://epaymentuat.acledabank.com.kh',
            (int) ($config['timeout']    ?? 30),
            (bool) ($config['verify_ssl'] ?? $config['verifySsl'] ?? true)
        );
    }

    public function getMerchantId(): string   { return $this->merchantId; }
    public function getLoginId(): string      { return $this->loginId; }
    public function getPassword(): string     { return $this->password; }
    public function getSecretKey(): string    { return $this->secretKey; }
    public function getMerchantName(): string { return $this->merchantName; }
    public function getBaseUrl(): string      { return $this->baseUrl; }
    public function getTimeout(): int         { return $this->timeout; }
    public function isVerifySsl(): bool       { return $this->verifySsl; }

    /** Root URL for this merchant: https://host/MERCHANTNAME */
    public function getMerchantBaseUrl(): string
    {
        return $this->baseUrl . '/' . $this->merchantName;
    }

    /** URL for XPAY API calls */
    public function getApiUrl(string $endpoint): string
    {
        return $this->getMerchantBaseUrl()
            . '/XPAYConnectorServiceInterfaceImplV2/XPAYConnectorServiceInterfaceImplV2RS/'
            . ltrim($endpoint, '/');
    }

    /** URL for the hosted payment page redirect */
    public function getPaymentPageUrl(): string
    {
        return $this->getMerchantBaseUrl() . '/paymentPage.jsp';
    }

    private function validate(
        string $merchantId,
        string $loginId,
        string $password,
        string $secretKey,
        string $merchantName
    ): void {
        $required = [
            'merchantId'   => $merchantId,
            'loginId'      => $loginId,
            'password'     => $password,
            'secretKey'    => $secretKey,
            'merchantName' => $merchantName,
        ];

        foreach ($required as $field => $value) {
            if (trim($value) === '') {
                throw new ValidationException("ToanchetPayConfig: '{$field}' must not be empty.");
            }
        }
    }
}
