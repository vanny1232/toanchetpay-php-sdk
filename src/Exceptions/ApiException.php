<?php

declare(strict_types=1);

namespace ToanchetPay\Exceptions;

/** Thrown when the ACLEDA API returns a non-zero result code. */
class ApiException extends ToanchetPayException
{
    private int $apiCode;
    private string $apiMessage;

    public function __construct(int $apiCode, string $apiMessage)
    {
        parent::__construct("ACLEDA API error [{$apiCode}]: {$apiMessage}", $apiCode);
        $this->apiCode    = $apiCode;
        $this->apiMessage = $apiMessage;
    }

    public function getApiCode(): int
    {
        return $this->apiCode;
    }

    public function getApiMessage(): string
    {
        return $this->apiMessage;
    }
}
