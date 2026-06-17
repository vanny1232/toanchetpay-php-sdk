<?php

declare(strict_types=1);

namespace ToanchetPay\Exceptions;

/** Thrown when the HTTP transport layer fails (connection error, timeout, non-2xx). */
class HttpException extends ToanchetPayException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
