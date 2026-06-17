<?php

declare(strict_types=1);

namespace ToanchetPay\DTO;

/**
 * Strongly-typed response from getTxnStatus.
 */
class TransactionStatusResponse
{
    public int    $code;
    public string $errorDetails;
    public string $coreRefNum;
    public string $txid;
    public string $invoiceId;
    public string $paymentTokenId;
    public float  $purchaseAmount;
    public int    $purchaseDate;
    public int    $confirmDate;
    public int    $quantity;
    public int    $expiryTime;
    public int    $purchaseType;
    public int    $saveToken;
    public int    $txDirection;
    public string $transactionDate;
    public string $maskedCard;

    /** @var array<string,mixed> Raw API result for advanced access. */
    public array $raw = [];

    /**
     * @param array<string,mixed> $apiResponse  Full decoded JSON from the API.
     */
    public static function fromApiResponse(array $apiResponse): self
    {
        $xTran = $apiResponse['xTran'] ?? [];

        $obj = new self();

        $obj->code            = (int)    ($apiResponse['code']             ?? -1);
        $obj->errorDetails    = (string) ($apiResponse['errorDetails']     ?? '');
        $obj->coreRefNum      = (string) ($apiResponse['coreRefNum']       ?? '');
        $obj->transactionDate = (string) ($apiResponse['transactionDate']  ?? '');
        $obj->txDirection     = (int)    ($apiResponse['TxDirection']      ?? 0);
        $obj->maskedCard      = (string) ($apiResponse['maskedCard']       ?? '');

        $obj->txid           = (string) ($xTran['txid']            ?? '');
        $obj->invoiceId      = (string) ($xTran['invoiceid']        ?? '');
        $obj->paymentTokenId = (string) ($xTran['paymentTokenid']   ?? '');
        $obj->purchaseAmount = (float)  ($xTran['purchaseAmount']   ?? 0.0);
        $obj->purchaseDate   = (int)    ($xTran['purchaseDate']     ?? 0);
        $obj->confirmDate    = (int)    ($xTran['confirmDate']      ?? 0);
        $obj->quantity       = (int)    ($xTran['quantity']         ?? 0);
        $obj->expiryTime     = (int)    ($xTran['expiryTime']       ?? 0);
        $obj->purchaseType   = (int)    ($xTran['purchaseType']     ?? 0);
        $obj->saveToken      = (int)    ($xTran['savetoken']        ?? 0);
        $obj->raw            = $apiResponse;

        return $obj;
    }

    /** Returns true when the transaction was successfully confirmed (code=0). */
    public function isSuccess(): bool
    {
        return $this->code === 0;
    }

    /** Returns true when the transaction is confirmed (confirmDate > 0). */
    public function isConfirmed(): bool
    {
        return $this->confirmDate > 0;
    }
}
