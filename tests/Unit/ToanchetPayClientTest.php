<?php

declare(strict_types=1);

namespace ToanchetPay\Tests\Unit;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\DTO\OpenSessionResponse;
use ToanchetPay\DTO\ToanchetPayTransaction;
use ToanchetPay\Exceptions\ApiException;
use ToanchetPay\Exceptions\HttpException;
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

    public function testGetTransactionStatusAcceptsWrappedResult(): void
    {
        $response = ['result' => ['code' => '0', 'xTran' => ['paymentTokenid' => 'TOKEN']]];
        $client = new ToanchetPayClient($this->config, $this->mockHttp($response));

        $status = $client->getTransactionStatus('TOKEN');

        $this->assertTrue($status->isSuccess());
        $this->assertFalse($status->isConfirmed());
        $this->assertSame('TOKEN', $status->paymentTokenId);
        $this->assertSame($response, $status->raw);
    }

    public function testOpenSessionSendsDocumentedRequest(): void
    {
        $capturedUrl = null;
        $capturedPayload = null;

        $mock = $this->createMock(HttpClientInterface::class);
        $mock->expects($this->once())
            ->method('postJson')
            ->willReturnCallback(function (string $url, array $payload) use (&$capturedUrl, &$capturedPayload) {
                $capturedUrl = $url;
                $capturedPayload = $payload;
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
        $this->assertSame($this->makeTransaction()->toArray(), $capturedPayload['xpayTransaction']);
        $this->assertSame(strtoupper(hash_hmac('sha512', 'TEST_MIDtestusertestpassTX001', 'test_secret')), $capturedPayload['hash']);
        $this->assertArrayNotHasKey('toanchetpayTransaction', $capturedPayload);
    }

    public function testRefundUsesUatRefundPortAndSignsPaymentToken(): void
    {
        $result = ['code' => 0, 'message' => 'SUCCESS', 'status' => 0];
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())->method('postJson')->with(
            'https://epaymentuat.acledabank.com.kh:8443/TESTMERCHANT/'
                . 'XPAYConnectorServiceInterfaceImplV2/XPAYConnectorServiceInterfaceImplV2RS/refundTxn',
            [
                'loginId' => 'testuser',
                'password' => 'testpass',
                'merchantId' => 'TEST_MID',
                'hash' => strtoupper(hash_hmac('sha512', 'TEST_MIDtestusertestpassTOKEN', 'test_secret')),
                'paymentTokenId' => 'TOKEN',
                'sessionId' => 'SESSION',
                'amount' => '0.50',
                'currency' => 'USD',
                'reason' => 'Cancelled item',
            ]
        )->willReturn(['result' => $result]);

        $client = new ToanchetPayClient($this->config, $http);
        $this->assertSame($result, $client->refundTransaction($this->makeSession(), '0.50', 'USD', 'Cancelled item'));
    }

    public function testRefundRejectsInvalidResultCode(): void
    {
        $this->expectException(HttpException::class);
        $http = $this->mockHttp(['result' => ['code' => 'unexpected']]);
        (new ToanchetPayClient($this->config, $http))->refundTransaction($this->makeSession(), '0.50', 'USD', 'Cancelled item');
    }

    public function testRefundSupportsSeparateBaseUrl(): void
    {
        $config = ToanchetPayConfig::fromArray([
            'merchant_id' => 'TEST_MID',
            'login_id' => 'testuser',
            'password' => 'testpass',
            'secret_key' => 'test_secret',
            'merchant_name' => 'TESTMERCHANT',
            'refund_base_url' => 'https://refund.example.com/',
        ]);

        $this->assertStringStartsWith('https://refund.example.com/TESTMERCHANT/', $config->getRefundApiUrl());
        $this->assertStringStartsWith('https://epaymentuat.acledabank.com.kh/TESTMERCHANT/', $config->getApiUrl('openSessionV2'));
    }

    public function testOpenTokenizationSessionSendsSignatureAndOperationType(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())->method('postJson')->with(
            $this->config->getApiUrl('openSessionV2'),
            [
                'loginId' => 'testuser',
                'password' => 'testpass',
                'merchantID' => 'TEST_MID',
                'signature' => 'TOKEN_SIGNATURE',
                'xpayTransaction' => ['operationType' => 2],
            ]
        )->willReturn(['result' => ['code' => 0, 'sessionid' => 'S1', 'xTran' => ['paymentTokenid' => 'T1']]]);

        $session = (new ToanchetPayClient($this->config, $http))->openTokenizationSession('TOKEN_SIGNATURE');
        $this->assertSame('T1', $session->paymentTokenId);
    }

    public function testPayTokenizeSendsDocumentedFieldsAndReturnsPaymentDetails(): void
    {
        $payment = [
            'paymentTokenId' => 'TOKEN',
            'payReference' => 'REF',
            'result' => ['code' => 0, 'message' => 'SUCCESS', 'status' => 0],
        ];
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())->method('postJson')->with(
            $this->config->getApiUrl('payTokenize'),
            [
                'loginId' => 'testuser',
                'password' => 'testpass',
                'merchantId' => 'TEST_MID',
                'secretCode' => 'TOKEN_SECRET',
                'tokenId' => '001',
                'transactionId' => 'TX001',
                'invoiceId' => 'INV001',
                'amount' => '1.50',
                'currency' => 'USD',
                'description' => 'Payment',
            ]
        )->willReturn(['result' => $payment]);

        $client = new ToanchetPayClient($this->config, $http);
        $this->assertSame($payment, $client->payTokenize('001', 'TX001', 'INV001', '1.50', 'USD', 'Payment', 'TOKEN_SECRET'));
    }

    public function testPayTokenizeThrowsNestedBankError(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(123);
        $this->expectExceptionMessage('Token declined');

        $http = $this->mockHttp(['result' => ['result' => ['code' => 123, 'message' => 'Token declined']]]);
        $client = new ToanchetPayClient($this->config, $http);
        $client->payTokenize('001', 'TX001', 'INV001', '1.50', 'USD', 'Payment', 'TOKEN_SECRET');
    }

    public function testDeleteTokenizationUsesMpgsSessionIds(): void
    {
        $result = ['code' => 0, 'errorDetails' => 'SUCCESS', 'tokenStatus' => 0];
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())->method('postJson')->with(
            $this->config->getApiUrl('deleteTokenization'),
            [
                'loginId' => 'testuser',
                'password' => 'testpass',
                'merchantID' => 'TEST_MID',
                'sessionId' => 'SESSION',
                'signature' => 'TOKEN_SIGNATURE',
                'token' => '001',
                'paymentTokenId' => 'TOKEN',
            ]
        )->willReturn(['result' => $result]);

        $client = new ToanchetPayClient($this->config, $http);
        $this->assertSame($result, $client->deleteTokenization('001', $this->makeSession(), 'TOKEN_SIGNATURE'));
    }

    private function makeSession(): OpenSessionResponse
    {
        return OpenSessionResponse::fromApiResponse(['sessionid' => 'SESSION', 'xTran' => ['paymentTokenid' => 'TOKEN']]);
    }
}
