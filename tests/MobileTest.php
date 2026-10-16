<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Mobile;
use ContiPay\PhpSdk\Tests\Support\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

class MobileTest extends TestCase
{
    private function mobile(?string $method = null): Mobile
    {
        return (new Mobile('test-token', 'test-secret', 'dev', $method))
            ->setMerchantId(116)
            ->setWebhookUrl('https://shop.test/webhook');
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '50.00',
            'currency' => 'USD',
            'phone' => '0782000340',
            'reference' => 'PAY-1',
        ];
    }

    public function test_direct_payment_posts_payload_for_provider(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile, [$this->ok(['status' => 'Success', 'ref' => 'PAY-1'])]);

        $response = $mobile->ecocash($this->fields());

        $this->assertSame(['status' => 'Success', 'ref' => 'PAY-1'], $this->decode($response));

        $request = $client->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api-uat.contipay.net/acquire/payment', (string) $request->getUri());
        $this->assertSame('Basic ' . base64_encode('test-token:test-secret'), $request->getHeaderLine('Authorization'));

        $transaction = $this->requestBody($client)['transaction'];
        $this->assertSame('EC', $transaction['providerCode']);
        $this->assertSame('EcoCash', $transaction['providerName']);
        $this->assertEquals(50.0, $transaction['amount']);
        $this->assertSame(116, $transaction['merchantId']);
        $this->assertSame('PAY-1', $transaction['reference']);
        $this->assertSame('https://shop.test/webhook', $transaction['webhookUrl']);
    }

    public function test_generic_mobile_uses_given_provider(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->mobile($this->fields(['provider' => 'InnBucks', 'code' => 'IB']));

        $transaction = $this->requestBody($client)['transaction'];
        $this->assertSame('IB', $transaction['providerCode']);
        $this->assertSame('InnBucks', $transaction['providerName']);
    }

    /**
     * @dataProvider providerMethods
     */
    public function test_each_provider_maps_to_its_name_and_code(string $method, string $name, string $code): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->{$method}($this->fields());

        $transaction = $this->requestBody($client)['transaction'];
        $this->assertSame($code, $transaction['providerCode']);
        $this->assertSame($name, $transaction['providerName']);
    }

    public static function providerMethods(): array
    {
        return [
            ['ecocash', 'EcoCash', 'EC'],
            ['onemoney', 'OneMoney', 'OM'],
            ['omari', 'Omari', 'OC'],
            ['innbucks', 'InnBucks', 'IB'],
            ['telecash', 'TeleCash', 'TC'],
            ['mtn', 'MTN', 'MN'],
            ['airtel', 'AirTel', 'AT'],
            ['zamtel', 'ZamTel', 'ZT'],
            ['equitel', 'EquiTel', 'ET'],
            ['mpesa', 'MPesa', 'MP'],
            ['orange', 'Orange', 'OG'],
            ['tigo', 'Tigo', 'TG'],
        ];
    }

    public function test_country_code_defaults_to_zw_and_can_be_overridden(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile, [$this->ok(), $this->ok()]);

        $mobile->ecocash($this->fields());
        $this->assertSame('ZW', $this->requestBody($client)['customer']['countryCode']);

        $mobile->mpesa($this->fields(['countryCode' => 'KE']));
        $this->assertSame('KE', $this->requestBody($client)['customer']['countryCode']);
    }

    public function test_redirect_payment_sends_put_with_redirect_urls(): void
    {
        $mobile = $this->mobile('redirect')
            ->setSuccessUrl('https://shop.test/success')
            ->setErrorUrl('https://shop.test/error');
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->ecocash($this->fields());

        $this->assertSame('PUT', $client->lastRequest()->getMethod());
        $body = $this->requestBody($client);
        $this->assertSame('https://shop.test/success', $body['successUrl']);
        $this->assertSame('https://shop.test/error', $body['cancelUrl']);
        $this->assertSame('PAY-1', $body['reference']);
    }

    public function test_live_mode_uses_live_url(): void
    {
        $mobile = (new Mobile('test-token', 'test-secret', 'live'))->setMerchantId(116);
        $client = $this->fake($mobile, [$this->ok()]);

        $mobile->ecocash($this->fields());

        $this->assertSame('api.contipay.net', $client->lastRequest()->getUri()->getHost());
    }

    public function test_missing_merchant_id_throws(): void
    {
        $mobile = new Mobile('test-token', 'test-secret');
        $client = $this->fake($mobile);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setMerchantId()');

        try {
            $mobile->ecocash($this->fields());
        } finally {
            $this->assertSame([], $client->history);
        }
    }

    public function test_missing_required_field_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: phone');

        $this->mobile()->ecocash($this->fields(['phone' => '']));
    }

    public function test_non_numeric_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount: 'abc'");

        $this->mobile()->ecocash($this->fields(['amount' => 'abc']));
    }

    public function test_non_numeric_amount_via_process_payment_returns_json_error(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile);

        $result = $this->decode($mobile->processPayment('abc', 'USD', '0782000340', 'PAY-1'));

        $this->assertSame('error', $result['status']);
        $this->assertSame([], $client->history);
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->mobile()->paypal($this->fields());
    }

    public function test_connection_failure_returns_json_error(): void
    {
        $mobile = $this->mobile();
        $this->fake($mobile, [new ConnectException('Connection refused', new Request('POST', '/'), null, ['errno' => 7])]);

        $result = $this->decode($mobile->ecocash($this->fields()));

        $this->assertSame(['status' => 'error', 'message' => 'Connection refused', 'outcome' => 'failed'], $result);
    }

    /**
     * @dataProvider invalidAmounts
     */
    public function test_invalid_amount_throws_before_sending($amount): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile);

        try {
            $mobile->ecocash($this->fields(['amount' => $amount]));
            $this->fail('Expected an invalid amount to throw.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid amount', $e->getMessage());
        }

        $this->assertSame([], $client->history);
    }

    public static function invalidAmounts(): array
    {
        return [
            'negative' => ['-5'],
            'negative float' => [-5.0],
            'exponent' => ['1e3'],
            'padded' => [' 5'],
            'three decimals' => ['1.005'],
            'zero with decimals' => ['0.00'],
            'array' => [[10]],
        ];
    }

    public function test_integer_and_float_amounts_are_accepted(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile, [$this->ok(), $this->ok()]);

        $mobile->ecocash($this->fields(['amount' => 10]));
        $this->assertEquals(10.0, $this->requestBody($client)['transaction']['amount']);

        $mobile->ecocash($this->fields(['amount' => 10.5]));
        $this->assertEquals(10.5, $this->requestBody($client)['transaction']['amount']);
    }

    public function test_negative_amount_via_process_payment_returns_json_error(): void
    {
        $mobile = $this->mobile();
        $client = $this->fake($mobile);

        $result = $this->decode($mobile->processPayment('-5', 'USD', '0782000340', 'PAY-1'));

        $this->assertSame('error', $result['status']);
        $this->assertSame([], $client->history);
    }
}
