<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Voucher;
use ContiPay\PhpSdk\Tests\Support\TestCase;

class VoucherTest extends TestCase
{
    private function voucher(?string $method = null): Voucher
    {
        return (new Voucher('test-token', 'test-secret', 'dev', $method))
            ->setMerchantId(116)
            ->setWebhookUrl('https://shop.test/webhook');
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '50.00',
            'currency' => 'USD',
            'phone' => '0782000340',
            'reference' => 'VOUCH-1',
        ];
    }

    public function test_direct_payment_posts_voucher_payload(): void
    {
        $voucher = $this->voucher();
        $client = $this->fake($voucher, [$this->ok(['status' => 'Success'])]);

        $voucher->voucher($this->fields());

        $request = $client->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api-uat.contipay.net/acquire/payment', (string) $request->getUri());

        $transaction = $this->requestBody($client)['transaction'];
        $this->assertSame('VC', $transaction['providerCode']);
        $this->assertSame('Voucher', $transaction['providerName']);
        $this->assertEquals(50.0, $transaction['amount']);
        $this->assertSame('VOUCH-1', $transaction['reference']);
    }

    /**
     * @dataProvider supportedCurrencies
     */
    public function test_supported_currencies_are_accepted(string $currency): void
    {
        $voucher = $this->voucher();
        $client = $this->fake($voucher, [$this->ok()]);

        $voucher->voucher($this->fields(['currency' => $currency]));

        $this->assertSame($currency, $this->requestBody($client)['transaction']['currencyCode']);
    }

    public static function supportedCurrencies(): array
    {
        return [['ZAR'], ['USD'], ['ZWG'], ['ZMW']];
    }

    public function test_unsupported_currency_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Currency 'GBP' is not supported by provider 'voucher'");

        $this->voucher()->voucher($this->fields(['currency' => 'GBP']));
    }

    public function test_mobile_providers_are_not_callable_on_voucher(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Provider 'ecocash' not supported.");

        $this->voucher()->ecocash($this->fields());
    }

    public function test_voucher_is_not_callable_on_mobile(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Provider 'voucher' not supported.");

        (new \ContiPay\PhpSdk\Mobile('test-token', 'test-secret'))
            ->setMerchantId(116)
            ->voucher($this->fields());
    }

    public function test_providers_lists_only_voucher(): void
    {
        $this->assertSame(['voucher'], array_keys($this->voucher()->providers()));
    }

    public function test_missing_required_field_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: reference');

        $this->voucher()->voucher($this->fields(['reference' => '']));
    }

    public function test_redirect_flow_sends_put_with_redirect_urls(): void
    {
        $voucher = $this->voucher('redirect')
            ->setSuccessUrl('https://shop.test/success')
            ->setErrorUrl('https://shop.test/error');
        $client = $this->fake($voucher, [$this->ok()]);

        $voucher->voucher($this->fields());

        $body = $this->requestBody($client);
        $this->assertSame('https://shop.test/success', $body['successUrl']);
        $this->assertSame('https://shop.test/error', $body['cancelUrl']);
    }
}
