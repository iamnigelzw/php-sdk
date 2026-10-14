<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Mobile;
use ContiPay\PhpSdk\Tests\Support\TestCase;

class StatusTest extends TestCase
{
    public function test_transaction_status_queries_acquire_endpoint(): void
    {
        $mobile = (new Mobile('test-token', 'test-secret'))->setMerchantId(116);
        $client = $this->fake($mobile, [$this->ok(['status' => 'Paid'])]);

        $result = $this->decode($mobile->getTransactionStatus('PAY-1'));

        $this->assertSame('Paid', $result['status']);
        $request = $client->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/acquire/payment', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['merchantRef' => 'PAY-1', 'merchantId' => '116'], $query);
    }

    public function test_disbursement_status_includes_provider_code(): void
    {
        $mobile = (new Mobile('test-token', 'test-secret'))->setMerchantId(116);
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->getDisbursementStatus('DISB-1', 'EC');

        $request = $client->lastRequest();
        $this->assertSame('/disburse/payment', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['merchantRef' => 'DISB-1', 'merchantId' => '116', 'providerCode' => 'EC'], $query);
    }

    public function test_status_without_merchant_id_omits_it(): void
    {
        $mobile = new Mobile('test-token', 'test-secret');
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->getTransactionStatus('PAY-1');

        parse_str($client->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame(['merchantRef' => 'PAY-1'], $query);
    }

    public function test_empty_reference_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Mobile('test-token', 'test-secret'))->getTransactionStatus('');
    }

    public function test_invalid_mode_returns_json_error(): void
    {
        $mobile = new Mobile('test-token', 'test-secret', 'staging');
        $this->fake($mobile);

        $result = $this->decode($mobile->getTransactionStatus('PAY-1'));

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString("Invalid mode 'STAGING'", $result['message']);
    }
}
