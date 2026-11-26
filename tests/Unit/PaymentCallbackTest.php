<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ToanchetPay\DTO\PaymentCallback;
use ToanchetPay\Exceptions\ValidationException;

class PaymentCallbackTest extends TestCase
{
    public function testParsesBankFieldNamesAndPreservesIdentifiers(): void
    {
        $callback = PaymentCallback::fromArray([
            '_paymenttokenid' => 'a+B/c=',
            '_resultCode' => '0',
            '_transactionid' => '000123',
            '_paymentresult' => 'SUCCESS',
            '_paymenttype' => 'CARD',
            '_amount' => '1.50',
            '_maskedCard' => '385555xxxx0005',
            'tokenization' => '000987',
        ]);

        $this->assertSame('a+B/c=', $callback->paymentTokenId);
        $this->assertSame(0, $callback->resultCode);
        $this->assertSame('000123', $callback->transactionId);
        $this->assertSame('1.50', $callback->amount);
        $this->assertSame('SUCCESS', $callback->paymentResult);
        $this->assertSame('CARD', $callback->paymentType);
        $this->assertSame('385555xxxx0005', $callback->maskedCard);
        $this->assertSame('000987', $callback->tokenization);
    }

    public function testRejectsNonNumericResultCode(): void
    {
        $this->expectException(ValidationException::class);
        PaymentCallback::fromArray(['_paymenttokenid' => 'TOKEN', '_resultCode' => 'SUCCESS']);
    }
}
