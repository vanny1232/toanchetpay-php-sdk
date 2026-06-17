<?php

declare(strict_types=1);

namespace ToanchetPay\DTO;

use ToanchetPay\Exceptions\ValidationException;

/**
 * Represents the toanchetpayTransaction object sent in openSessionV2.
 *
 * Use the static factory methods to get a pre-configured builder for each
 * payment type, then call build() to produce the final array.
 *
 * Payment type guide:
 *   standard()  → ACLEDA hosted page (card/ABA/Wing/etc.)
 *   mpgs()      → Mastercard Payment Gateway (paymentCard=1)
 *   khqr()      → KHQR via hosted page (operationType=3)
 *   khqrPos()   → KHQR POS / kiosk terminal (operationType=5)
 *   deeplink()  → Mobile app deeplink (oprDevice + callBackUrl)
 */
class ToanchetPayTransaction
{
    // --- Required ---
    private string $txid;
    private string $purchaseAmount;
    private string $purchaseCurrency;
    private string $purchaseDate;
    private string $purchaseDesc;
    private string $invoiceid;
    private string $item;
    private string $quantity;
    private string $expiryTime;

    // --- Optional (payment-type specific) ---
    private ?string $paymentCard    = null;
    private ?string $operationType  = null;
    private ?string $terminalId     = null;
    private ?string $counterId      = null;
    private ?string $oprDevice      = null;
    private ?string $callBackUrl    = null;

    private function __construct(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $item        = '1',
        string $quantity    = '1',
        string $expiryTime  = '5'
    ) {
        $this->txid             = $txid;
        $this->purchaseAmount   = $purchaseAmount;
        $this->purchaseCurrency = $purchaseCurrency;
        $this->purchaseDate     = $purchaseDate;
        $this->purchaseDesc     = $purchaseDesc;
        $this->invoiceid        = $invoiceid;
        $this->item             = $item;
        $this->quantity         = $quantity;
        $this->expiryTime       = $expiryTime;
    }

    // -------------------------------------------------------------------------
    // Static factory methods
    // -------------------------------------------------------------------------

    /** Standard ACLEDA hosted payment page (no extra fields). */
    public static function standard(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $item       = '1',
        string $quantity   = '1',
        string $expiryTime = '5'
    ): self {
        return new self(
            $txid, $purchaseAmount, $purchaseCurrency,
            $purchaseDate, $purchaseDesc, $invoiceid,
            $item, $quantity, $expiryTime
        );
    }

    /** MPGS (Mastercard Payment Gateway) — adds paymentCard=1. */
    public static function mpgs(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $item       = '1',
        string $quantity   = '1',
        string $expiryTime = '5'
    ): self {
        $tx = new self(
            $txid, $purchaseAmount, $purchaseCurrency,
            $purchaseDate, $purchaseDesc, $invoiceid,
            $item, $quantity, $expiryTime
        );
        $tx->paymentCard = '1';
        return $tx;
    }

    /** KHQR via hosted page — adds operationType=3. */
    public static function khqr(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $item       = '1',
        string $quantity   = '1',
        string $expiryTime = '5'
    ): self {
        $tx = new self(
            $txid, $purchaseAmount, $purchaseCurrency,
            $purchaseDate, $purchaseDesc, $invoiceid,
            $item, $quantity, $expiryTime
        );
        $tx->operationType = '3';
        return $tx;
    }

    /** KHQR for POS / kiosk — adds operationType=5 + terminal info. */
    public static function khqrPos(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $terminalId,
        string $counterId,
        string $item       = '1',
        string $quantity   = '1',
        string $expiryTime = '5'
    ): self {
        $tx = new self(
            $txid, $purchaseAmount, $purchaseCurrency,
            $purchaseDate, $purchaseDesc, $invoiceid,
            $item, $quantity, $expiryTime
        );
        $tx->operationType = '5';
        $tx->terminalId    = $terminalId;
        $tx->counterId     = $counterId;
        return $tx;
    }

    /** Mobile deeplink — adds oprDevice + callBackUrl. */
    public static function deeplink(
        string $txid,
        string $purchaseAmount,
        string $purchaseCurrency,
        string $purchaseDate,
        string $purchaseDesc,
        string $invoiceid,
        string $oprDevice,
        string $callBackUrl,
        string $item       = '1',
        string $quantity   = '1',
        string $expiryTime = '5'
    ): self {
        $tx = new self(
            $txid, $purchaseAmount, $purchaseCurrency,
            $purchaseDate, $purchaseDesc, $invoiceid,
            $item, $quantity, $expiryTime
        );
        $tx->oprDevice   = $oprDevice;
        $tx->callBackUrl = $callBackUrl;
        return $tx;
    }

    // -------------------------------------------------------------------------
    // Fluent setters — for overriding individual fields after construction
    // -------------------------------------------------------------------------

    public function withPaymentCard(string $paymentCard): self
    {
        $this->paymentCard = $paymentCard;
        return $this;
    }

    public function withOperationType(string $type): self
    {
        $this->operationType = $type;
        return $this;
    }

    public function withTerminal(string $terminalId, string $counterId): self
    {
        $this->terminalId = $terminalId;
        $this->counterId  = $counterId;
        return $this;
    }

    public function withDeeplink(string $oprDevice, string $callBackUrl): self
    {
        $this->oprDevice   = $oprDevice;
        $this->callBackUrl = $callBackUrl;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getTxid(): string { return $this->txid; }

    // -------------------------------------------------------------------------
    // Serialisation
    // -------------------------------------------------------------------------

    /**
     * Return the payload array ready to embed in the API request.
     *
     * @return array<string,string>
     */
    public function toArray(): array
    {
        $this->assertValid();

        $data = [
            'txid'            => $this->txid,
            'purchaseAmount'  => $this->purchaseAmount,
            'purchaseCurrency'=> $this->purchaseCurrency,
            'purchaseDate'    => $this->purchaseDate,
            'purchaseDesc'    => $this->purchaseDesc,
            'invoiceid'       => $this->invoiceid,
            'item'            => $this->item,
            'quantity'        => $this->quantity,
            'expiryTime'      => $this->expiryTime,
        ];

        if ($this->paymentCard !== null)   { $data['paymentCard']   = $this->paymentCard; }
        if ($this->operationType !== null) { $data['operationType'] = $this->operationType; }
        if ($this->terminalId !== null)    { $data['terminalId']    = $this->terminalId; }
        if ($this->counterId !== null)     { $data['counterId']     = $this->counterId; }
        if ($this->oprDevice !== null)     { $data['oprDevice']     = $this->oprDevice; }
        if ($this->callBackUrl !== null)   { $data['callBackUrl']   = $this->callBackUrl; }

        return $data;
    }

    private function assertValid(): void
    {
        $required = [
            'txid'            => $this->txid,
            'purchaseAmount'  => $this->purchaseAmount,
            'purchaseCurrency'=> $this->purchaseCurrency,
            'purchaseDate'    => $this->purchaseDate,
            'purchaseDesc'    => $this->purchaseDesc,
            'invoiceid'       => $this->invoiceid,
        ];

        foreach ($required as $field => $value) {
            if (trim($value) === '') {
                throw new ValidationException("ToanchetPayTransaction: '{$field}' must not be empty.");
            }
        }

        if ($this->operationType === '5' && ($this->terminalId === null || $this->counterId === null)) {
            throw new ValidationException(
                'ToanchetPayTransaction: operationType=5 (KHQR POS) requires terminalId and counterId.'
            );
        }
    }
}
