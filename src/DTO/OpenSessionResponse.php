<?php

declare(strict_types=1);

namespace ToanchetPay\DTO;

/**
 * Strongly-typed response from openSessionV2.
 */
class OpenSessionResponse
{
    public string $sessionId;
    public string $paymentTokenId;
    public float  $purchaseAmount;
    public int    $purchaseDate;
    public int    $quantity;
    public int    $expiryTime;
    public float  $feeAmount;
    public int    $txDirection;

    /** Present when operationType = 3 (KHQR web) or 5 (KHQR POS). */
    public ?string $qrValue = null;

    /** Present when oprDevice + callBackUrl are provided (mobile deeplink). */
    public ?string $deeplinkUrl = null;

    /** @var array<string,mixed> Raw API result for advanced access. */
    public array $raw = [];

    /**
     * @param array<string,mixed> $apiResponse  Full decoded JSON from the API.
     */
    public static function fromApiResponse(array $apiResponse): self
    {
        $result = $apiResponse['result'] ?? $apiResponse;
        $xTran  = $result['xTran'] ?? [];

        $obj = new self();

        $obj->sessionId      = (string) ($result['sessionid']               ?? '');
        $obj->paymentTokenId = (string) ($xTran['paymentTokenid']           ?? '');
        $obj->purchaseAmount = (float)  ($xTran['purchaseAmount']           ?? 0.0);
        $obj->purchaseDate   = (int)    ($xTran['purchaseDate']             ?? 0);
        $obj->quantity       = (int)    ($xTran['quantity']                 ?? 0);
        $obj->expiryTime     = (int)    ($xTran['expiryTime']               ?? 0);
        $obj->feeAmount      = (float)  ($xTran['feeAmount']                ?? 0.0);
        $obj->txDirection    = (int)    ($result['TxDirection']             ?? 0);
        $obj->qrValue        = isset($result['qrValue'])    ? (string) $result['qrValue']    : null;
        $obj->deeplinkUrl    = isset($result['deeplinkUrl'])? (string) $result['deeplinkUrl']: null;
        $obj->raw            = $apiResponse;

        return $obj;
    }

    /** Whether the response contains a KHQR value for QR image generation. */
    public function hasQrValue(): bool
    {
        return $this->qrValue !== null && $this->qrValue !== '';
    }

    /** Whether the response contains a mobile deeplink URL. */
    public function hasDeeplinkUrl(): bool
    {
        return $this->deeplinkUrl !== null && $this->deeplinkUrl !== '';
    }
}
