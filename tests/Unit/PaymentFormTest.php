<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\Exceptions\ValidationException;
use ToanchetPay\Helpers\PaymentForm;
use PHPUnit\Framework\TestCase;

class PaymentFormTest extends TestCase
{
    private ToanchetPayConfig  $config;
    private PaymentForm $form;

    protected function setUp(): void
    {
        $this->config = new ToanchetPayConfig(
            'TEST_MID',
            'testuser',
            'testpass',
            'secret',
            'TESTMERCHANT'
        );

        $this->form = new PaymentForm($this->config);
    }

    private function makeSession(string $sessionId = 'S1', string $tokenId = 'T1'): OpenSessionResponse
    {
        return OpenSessionResponse::fromApiResponse([
            'result' => [
                'code'         => 0,
                'errorDetails' => 'SUCCESS',
                'sessionid'    => $sessionId,
                'xTran'        => [
                    'paymentTokenid' => $tokenId,
                    'purchaseAmount' => 1.0,
                    'purchaseDate'   => 0,
                    'quantity'       => 1,
                    'expiryTime'     => 5,
                    'feeAmount'      => 0.0,
                ],
                'TxDirection' => 0,
            ],
        ]);
    }

    private function txData(): array
    {
        return [
            'txid'            => 'TX001',
            'purchaseAmount'  => '1.00',
            'purchaseCurrency'=> 'USD',
            'purchaseDate'    => '01-01-2024',
            'purchaseDesc'    => 'Test Order',
            'invoiceid'       => 'INV001',
            'quantity'        => '1',
            'item'            => '1',
            'expiryTime'      => '5',
        ];
    }

    public function testFormContainsActionUrl(): void
    {
        $html = $this->form->generate(
            $this->makeSession(), $this->txData(),
            'https://site.com/success', 'https://site.com/failed'
        );

        $this->assertStringContainsString('action="', $html);
        $this->assertStringContainsString('TESTMERCHANT', $html);
        $this->assertStringContainsString('paymentPage.jsp', $html);
    }

    public function testFormContainsSessionId(): void
    {
        $html = $this->form->generate(
            $this->makeSession('MY_SESSION', 'MY_TOKEN'), $this->txData(),
            'https://site.com/success', 'https://site.com/failed'
        );

        $this->assertStringContainsString('MY_SESSION', $html);
        $this->assertStringContainsString('MY_TOKEN',   $html);
    }

    public function testFormContainsMerchantId(): void
    {
        $html = $this->form->generate(
            $this->makeSession(), $this->txData(),
            'https://site.com/success', 'https://site.com/failed'
        );

        $this->assertStringContainsString('TEST_MID', $html);
    }

    public function testFormContainsSuccessAndErrorUrls(): void
    {
        $html = $this->form->generate(
            $this->makeSession(), $this->txData(),
            'https://site.com/success', 'https://site.com/failed'
        );

        $this->assertStringContainsString('https://site.com/success', $html);
        $this->assertStringContainsString('https://site.com/failed',  $html);
    }

    public function testFormContainsAutoSubmitScript(): void
    {
        $html = $this->form->generate(
            $this->makeSession(), $this->txData(),
            'https://s.com/ok', 'https://s.com/err'
        );

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('.submit()', $html);
    }

    public function testEmptySessionIdThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $emptySession = OpenSessionResponse::fromApiResponse([
            'result' => [
                'code'         => 0,
                'errorDetails' => 'SUCCESS',
                'sessionid'    => '',
                'xTran'        => ['paymentTokenid' => '', 'purchaseAmount' => 0,
                                   'purchaseDate' => 0, 'quantity' => 0, 'expiryTime' => 0, 'feeAmount' => 0],
                'TxDirection'  => 0,
            ],
        ]);

        $this->form->generate($emptySession, $this->txData(), 'https://s.com', 'https://s.com');
    }

    public function testHtmlIsProperlyEscaped(): void
    {
        $session = $this->makeSession('<script>alert(1)</script>', 'T1');
        $html    = $this->form->generate(
            $session, $this->txData(), 'https://s.com', 'https://s.com'
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
