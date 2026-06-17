<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use ToanchetPay\Security\HashGenerator;
use PHPUnit\Framework\TestCase;

class HashGeneratorTest extends TestCase
{
    private HashGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new HashGenerator('test_secret_key_12345');
    }

    public function testGenerateReturnsUppercaseHex(): void
    {
        $hash = $this->generator->generate('hello');
        $this->assertMatchesRegularExpression('/^[0-9A-F]+$/', $hash);
    }

    public function testGenerateReturnsSha512Length(): void
    {
        // SHA-512 = 512 bits = 64 bytes = 128 hex chars
        $hash = $this->generator->generate('test message');
        $this->assertSame(128, strlen($hash));
    }

    public function testForSessionConcatenatesInCorrectOrder(): void
    {
        $merchantId = 'MID';
        $loginId    = 'USER';
        $password   = 'PASS';
        $txid       = 'TX001';

        $expected = $this->generator->generate($merchantId . $loginId . $password . $txid);
        $actual   = $this->generator->forSession($merchantId, $loginId, $password, $txid);

        $this->assertSame($expected, $actual);
    }

    public function testForStatusConcatenatesInCorrectOrder(): void
    {
        $merchantId     = 'MID';
        $loginId        = 'USER';
        $password       = 'PASS';
        $paymentTokenId = 'TOKEN123';

        $expected = $this->generator->generate($merchantId . $loginId . $password . $paymentTokenId);
        $actual   = $this->generator->forStatus($merchantId, $loginId, $password, $paymentTokenId);

        $this->assertSame($expected, $actual);
    }

    public function testDifferentInputsProduceDifferentHashes(): void
    {
        $hash1 = $this->generator->generate('message_one');
        $hash2 = $this->generator->generate('message_two');

        $this->assertNotSame($hash1, $hash2);
    }

    public function testSameInputProducesSameHash(): void
    {
        $hash1 = $this->generator->generate('deterministic');
        $hash2 = $this->generator->generate('deterministic');

        $this->assertSame($hash1, $hash2);
    }

    public function testKnownVector(): void
    {
        // Pre-computed: echo -n "hello" | openssl dgst -sha512 -hmac "secret" | awk '{print toupper($2)}'
        $gen  = new HashGenerator('secret');
        $hash = $gen->generate('hello');

        $this->assertSame(
            strtoupper(hash_hmac('sha512', 'hello', 'secret')),
            $hash
        );
    }
}
