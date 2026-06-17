<?php

declare(strict_types=1);

namespace ToanchetPay\Helpers;

use ToanchetPay\Exceptions\ToanchetPayException;

/**
 * Optional helper to turn a KHQR qrValue string into a PNG image.
 *
 * Requires: composer require endroid/qr-code:^4.0|^5.0
 *
 * Usage:
 *   $qr = new QrCodeGenerator();
 *   $png    = $qr->toPng($session->qrValue);
 *   $base64 = $qr->toBase64DataUri($session->qrValue);
 *   file_put_contents('qr.png', $png);
 */
class QrCodeGenerator
{
    private int $size;
    private int $margin;

    public function __construct(int $size = 300, int $margin = 10)
    {
        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            throw new ToanchetPayException(
                'endroid/qr-code is not installed. Run: composer require endroid/qr-code'
            );
        }

        $this->size   = $size;
        $this->margin = $margin;
    }

    /**
     * Returns raw PNG binary data.
     */
    public function toPng(string $qrValue): string
    {
        $this->assertNotEmpty($qrValue);

        // Compatible with both v4 and v5 of endroid/qr-code
        if (class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            return $this->buildV4V5($qrValue);
        }

        throw new ToanchetPayException('Unsupported endroid/qr-code version. Please use ^4.0 or ^5.0.');
    }

    /**
     * Returns a base64 data URI suitable for use in an <img src="..."> tag.
     * Example: data:image/png;base64,iVBOR...
     */
    public function toBase64DataUri(string $qrValue): string
    {
        return 'data:image/png;base64,' . base64_encode($this->toPng($qrValue));
    }

    private function buildV4V5(string $qrValue): string
    {
        $result = \Endroid\QrCode\Builder\Builder::create()
            ->data($qrValue)
            ->encoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
            ->errorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium())
            ->size($this->size)
            ->margin($this->margin)
            ->build();

        return $result->getString();
    }

    private function assertNotEmpty(string $qrValue): void
    {
        if (trim($qrValue) === '') {
            throw new ToanchetPayException(
                'QrCodeGenerator: qrValue is empty. Make sure openSessionV2 returned a qrValue '
                . '(operationType must be 3 or 5).'
            );
        }
    }
}
