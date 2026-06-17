<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ToanchetPayTransactionTest extends TestCase
{
    private function makeStandard(array $overrides = []): ToanchetPayTransaction
    {
        return ToanchetPayTransaction::standard(
            $overrides['txid']             ?? 'TX001',
            $overrides['purchaseAmount']   ?? '1.00',
            $overrides['purchaseCurrency'] ?? 'USD',
            $overrides['purchaseDate']     ?? '01-01-2024',
            $overrides['purchaseDesc']     ?? 'Test',
            $overrides['invoiceid']        ?? 'INV001'
        );
    }

    public function testStandardTransactionContainsRequiredFields(): void
    {
        $tx   = $this->makeStandard();
        $data = $tx->toArray();

        $this->assertArrayHasKey('txid',             $data);
        $this->assertArrayHasKey('purchaseAmount',   $data);
        $this->assertArrayHasKey('purchaseCurrency', $data);
        $this->assertArrayHasKey('purchaseDate',     $data);
        $this->assertArrayHasKey('purchaseDesc',     $data);
        $this->assertArrayHasKey('invoiceid',        $data);
    }

    public function testStandardTransactionHasNoOptionalFields(): void
    {
        $tx   = $this->makeStandard();
        $data = $tx->toArray();

        $this->assertArrayNotHasKey('paymentCard',   $data);
        $this->assertArrayNotHasKey('operationType', $data);
        $this->assertArrayNotHasKey('oprDevice',     $data);
        $this->assertArrayNotHasKey('callBackUrl',   $data);
    }

    public function testMpgsAddsPaymentCard(): void
    {
        $tx = ToanchetPayTransaction::mpgs('TX', '1', 'USD', '01-01-2024', 'Test', 'INV');
        $this->assertSame('1', $tx->toArray()['paymentCard']);
    }

    public function testKhqrAddsOperationType3(): void
    {
        $tx = ToanchetPayTransaction::khqr('TX', '1', 'USD', '01-01-2024', 'Test', 'INV');
        $this->assertSame('3', $tx->toArray()['operationType']);
    }

    public function testKhqrPosAddsOperationType5AndTerminal(): void
    {
        $tx   = ToanchetPayTransaction::khqrPos('TX', '1', 'USD', '01-01-2024', 'Test', 'INV', 'T001', 'Counter1');
        $data = $tx->toArray();

        $this->assertSame('5',        $data['operationType']);
        $this->assertSame('T001',     $data['terminalId']);
        $this->assertSame('Counter1', $data['counterId']);
    }

    public function testDeeplinkAddsOprDeviceAndCallbackUrl(): void
    {
        $tx   = ToanchetPayTransaction::deeplink('TX', '1', 'USD', '01-01-2024', 'Test', 'INV', 'android', 'app://cb');
        $data = $tx->toArray();

        $this->assertSame('android',  $data['oprDevice']);
        $this->assertSame('app://cb', $data['callBackUrl']);
    }

    public function testEmptyTxidThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        ToanchetPayTransaction::standard('', '1', 'USD', '01-01-2024', 'Test', 'INV')->toArray();
    }

    public function testKhqrPosMissingTerminalThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $tx = ToanchetPayTransaction::standard('TX', '1', 'USD', '01-01-2024', 'Test', 'INV');
        $tx->withOperationType('5'); // operationType=5 without terminal
        $tx->toArray();
    }

    public function testGetTxidReturnsCorrectValue(): void
    {
        $tx = $this->makeStandard(['txid' => 'MY_TX_ID']);
        $this->assertSame('MY_TX_ID', $tx->getTxid());
    }

    public function testFluentWithPaymentCard(): void
    {
        $tx   = $this->makeStandard()->withPaymentCard('1');
        $data = $tx->toArray();
        $this->assertSame('1', $data['paymentCard']);
    }
}
