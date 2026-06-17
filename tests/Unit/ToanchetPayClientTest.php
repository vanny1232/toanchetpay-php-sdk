<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Http\HttpClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ToanchetPayClientTest extends TestCase
{
    private ToanchetPayConfig $config;

    protected function setUp(): void
    {
        $this->config = new ToanchetPayConfig(
            'TEST_MID',
            'testuser',
            'testpass',
            'test_secret',
            'TESTMERCHANT'
        );
    }

    private function makeTransaction(): ToanchetPayTransaction
    {
        return ToanchetPayTransaction::standard(
            'TX001',
            '1.00',
            'USD',
            '01-01-2024',
            'Test',
            'INV001'
        );
    }

    /** @return MockObject&HttpClientInterface */
    private function mockHttp(array $response): MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $mock->method('postJson')->willReturn($response);
        return $mock;
    }

    public function testOpenSessionReturnsCorrectSessionId(): void
    {
        $http = $this->mockHttp([
            'result' => [
                'code'         => 0,
                'errorDetails' => 'SUCCESS',
                'sessionid'    => 'SESSION_ABC',
                'xTran'        => [
                    'paymentTokenid' => 'TOKEN_XYZ',
                    'purchaseAmount' => 1.0,
                    'purchaseDate'   => 1700000000000,
                    'quantity'       => 1,
                    'expiryTime'     => 5,
                    'feeAmount'      => 0.0,
                ],
                'TxDirection' => 0,
            ],
        ]);

        $client   = new ToanchetPayClient($this->config, $http);
        $response = $client->openSession($this->makeTransaction());

        $this->assertSame('SESSION_ABC', $response->sessionId);
        $this->assertSame('TOKEN_XYZ',  $response->paymentTokenId);
        $this->assertSame(1.0,          $response->purchaseAmount);
    }

    public function testOpenSessionWithQrValue(): void
    {
        $http = $this->mockHttp([
            'result' => [
                'code'         => 0,
                'errorDetails' => 'SUCCESS',
                'sessionid'    => 'S123',
                'xTran'        => ['paymentTokenid' => 'T456', 'purchaseAmount' => 5.0,
                                   'purchaseDate' => 0, 'quantity' => 1, 'expiryTime' => 5, 'feeAmount' => 0.0],
                'TxDirection' => 0,
                'qrValue'     => '000201SAMPLEQRVALUE',
            ],
        ]);

        $client   = new ToanchetPayClient($this->config, $http);
        $response = $client->openSession($this->makeTransaction());

        $this->assertTrue($response->hasQrValue());
        $this->assertSame('000201SAMPLEQRVALUE', $response->qrValue);
    }

    public function testOpenSessionThrowsApiExceptionOnNonZeroCode(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('INVALID_MERCHANT');

        $http = $this->mockHttp([
            'result' => ['code' => 5, 'errorDetails' => 'INVALID_MERCHANT'],
        ]);

        $client = new ToanchetPayClient($this->config, $http);
        $client->openSession($this->makeTransaction());
    }

    public function testGetTransactionStatusSuccess(): void
    {
        $http = $this->mockHttp([
            'code'            => 0,
            'errorDetails'    => 'SUCCESS',
            'coreRefNum'      => 'FT123456',
            'transactionDate' => '2024-01-01 10:00:00',
            'TxDirection'     => 0,
            'xTran'           => [
                'txid'           => 'TX001',
                'invoiceid'      => 'INV001',
                'paymentTokenid' => 'TOKEN_XYZ',
                'purchaseAmount' => 1.0,
                'purchaseDate'   => 1700000000000,
                'confirmDate'    => 1700000100000,
                'quantity'       => 1,
                'expiryTime'     => 5,
                'purchaseType'   => 0,
                'savetoken'      => 0,
            ],
        ]);

        $client = new ToanchetPayClient($this->config, $http);
        $status = $client->getTransactionStatus('TOKEN_XYZ');

        $this->assertTrue($status->isSuccess());
        $this->assertTrue($status->isConfirmed());
        $this->assertSame('FT123456', $status->coreRefNum);
        $this->assertSame(1.0,        $status->purchaseAmount);
    }

    public function testGetTransactionStatusThrowsApiExceptionOnError(): void
    {
        $this->expectException(ApiException::class);

        $http = $this->mockHttp(['code' => 3, 'errorDetails' => 'TOKEN_NOT_FOUND']);
        $client = new ToanchetPayClient($this->config, $http);
        $client->getTransactionStatus('INVALID_TOKEN');
    }

    public function testHttpClientReceivesCorrectApiUrl(): void
    {
        $capturedUrl = null;

        $mock = $this->createMock(HttpClientInterface::class);
        $mock->expects($this->once())
            ->method('postJson')
            ->willReturnCallback(function (string $url, array $payload) use (&$capturedUrl) {
                $capturedUrl = $url;
                return [
                    'result' => [
                        'code' => 0, 'errorDetails' => 'SUCCESS', 'sessionid' => 'S1',
                        'xTran' => ['paymentTokenid' => 'T1', 'purchaseAmount' => 1.0,
                                    'purchaseDate' => 0, 'quantity' => 1, 'expiryTime' => 5, 'feeAmount' => 0.0],
                        'TxDirection' => 0,
                    ],
                ];
            });

        $client = new ToanchetPayClient($this->config, $mock);
        $client->openSession($this->makeTransaction());

        $this->assertStringContainsString('openSessionV2', (string) $capturedUrl);
        $this->assertStringContainsString('TESTMERCHANT',  (string) $capturedUrl);
    }
}
