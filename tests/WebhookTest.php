<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    /**
     * A paid EcoCash notification, as sent by ContiPay.
     */
    private const PAID = '{
        "account": "263***012",
        "amount": 34,
        "merchantId": 116,
        "merchantCharge": 0.51,
        "customerCharge": 0,
        "clientKey": "azJEL29jcEFUc3B0eENkTWxNdmx2UT09",
        "contiPayRef": 37889486,
        "firstName": 263778914012,
        "lastName": "-",
        "email": "263***.zw",
        "merchantRef": "VSELL-YWXTZYLNQ8SV",
        "message": "COMPLETED",
        "methodCode": "acquire",
        "currencyCode": "USD",
        "providerCode": "EC",
        "providerName": "EcoCash",
        "correlator": "MP261014.1305.T0858584",
        "statusCode": 1,
        "status": "paid"
    }';

    public function test_reads_paid_notification(): void
    {
        $webhook = Webhook::fromJson(self::PAID);

        $this->assertSame(Webhook::STATUS_PAID, $webhook->statusCode());
        $this->assertTrue($webhook->isPaid());
        $this->assertFalse($webhook->isPending());
        $this->assertFalse($webhook->isFailed());
        $this->assertSame('VSELL-YWXTZYLNQ8SV', $webhook->reference());
        $this->assertSame('37889486', $webhook->contiPayReference());
        $this->assertSame('MP261014.1305.T0858584', $webhook->providerReference());
        $this->assertSame(116, $webhook->merchantId());
        $this->assertSame(34.0, $webhook->amount());
        $this->assertSame('USD', $webhook->currency());
        $this->assertSame('EC', $webhook->providerCode());
        $this->assertSame('EcoCash', $webhook->providerName());
        $this->assertSame('acquire', $webhook->methodCode());
        $this->assertSame('COMPLETED', $webhook->message());
        $this->assertSame(0.51, $webhook->get('merchantCharge'));
        $this->assertSame('fallback', $webhook->get('missing', 'fallback'));
    }

    public function test_reads_status_response_with_nested_transaction(): void
    {
        $webhook = new Webhook([
            'status' => 'Paid',
            'statusCode' => 1,
            'message' => 'Transaction for ref:PAY-1 Found ',
            'transaction' => [
                'amount' => 1,
                'merchantId' => 1,
                'contiPayRef' => 57608,
                'merchantRef' => 'PAY-1',
                'statusCode' => 1,
                'status' => 'paid',
            ],
        ]);

        $this->assertTrue($webhook->isPaid());
        $this->assertSame('PAY-1', $webhook->reference());
        $this->assertSame('57608', $webhook->contiPayReference());
    }

    public function test_status_error_response_is_failed(): void
    {
        $webhook = new Webhook(['status' => 'Error', 'statusCode' => 3, 'message' => 'Provider code: does not exist!']);

        $this->assertTrue($webhook->isFailed());
        $this->assertNull($webhook->reference());
    }

    public static function statuses(): array
    {
        return [
            'pending' => [0, 'pending'],
            'paid' => [1, 'paid'],
            'refunded' => [2, 'refunded'],
            'error' => [3, 'failed'],
            'declined' => [4, 'failed'],
            'confirmed' => [5, 'paid'],
            'queued' => [6, 'pending'],
            'approved' => [7, 'pending'],
            'submitted' => [8, 'pending'],
            'unknown' => [99, 'failed'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_codes_are_classified(int $code, string $expected): void
    {
        $webhook = new Webhook(['statusCode' => $code]);

        $this->assertSame([
            'paid' => $expected === 'paid',
            'pending' => $expected === 'pending',
            'refunded' => $expected === 'refunded',
            'failed' => $expected === 'failed',
        ], [
            'paid' => $webhook->isPaid(),
            'pending' => $webhook->isPending(),
            'refunded' => $webhook->isRefunded(),
            'failed' => $webhook->isFailed(),
        ]);
    }

    public function test_textual_status_is_used_without_status_code(): void
    {
        $this->assertSame(Webhook::STATUS_PAID, (new Webhook(['status' => 'Paid']))->statusCode());
        $this->assertSame(Webhook::STATUS_PENDING, (new Webhook(['status' => 'PENDING']))->statusCode());
        $this->assertSame(Webhook::STATUS_DECLINED, (new Webhook(['status' => 'declined']))->statusCode());
    }

    public function test_numeric_string_status_code(): void
    {
        $this->assertSame(Webhook::STATUS_CONFIRMED, (new Webhook(['statusCode' => '5']))->statusCode());
    }

    public function test_missing_or_unknown_status_is_never_paid(): void
    {
        $this->assertTrue((new Webhook([]))->isFailed());
        $this->assertTrue((new Webhook(['status' => 'COMPLETED']))->isFailed());
    }

    public function test_invalid_json_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Webhook::fromJson('not json');
    }

    public function test_scalar_json_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Webhook::fromJson('"paid"');
    }
}
