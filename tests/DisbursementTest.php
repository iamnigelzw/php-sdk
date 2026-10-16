<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Disbursement;
use ContiPay\PhpSdk\Tests\Support\TestCase;

class DisbursementTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        $this->keys = $this->keyPair();
    }

    private function disbursement(): Disbursement
    {
        return (new Disbursement('test-token', 'test-secret', $this->keys['private'], 'dev'))
            ->setMerchantId(116)
            ->setWebhookUrl('https://shop.test/webhook');
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '20.00',
            'currency' => 'USD',
            'phone' => '0782000340',
            'reference' => 'DISB-1',
        ];
    }

    public function test_disbursement_is_signed_and_posted(): void
    {
        $disbursement = $this->disbursement();
        $client = $this->fake($disbursement, [$this->ok()]);

        $disbursement->onemoney($this->fields());

        $request = $client->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/disburse/payment', $request->getUri()->getPath());

        $body = $this->requestBody($client);
        $this->assertSame('OM', $body['transaction']['providerCode']);
        $this->assertSame('0782000340', $body['accountDetails']['accountNumber']);

        $signed = 'test-token' . 'DISB-1' . '116' . '0782000340' . '20';
        $signature = base64_decode($request->getHeaderLine('checksum'), true);
        $this->assertSame(1, openssl_verify($signed, $signature, $this->keys['public'], OPENSSL_ALGO_SHA256));
    }

    /**
     * @dataProvider payoutProviders
     */
    public function test_each_payout_provider_maps_to_its_name_and_code(string $method, string $name, string $code): void
    {
        $disbursement = $this->disbursement();
        $client = $this->fake($disbursement, [$this->ok()]);

        $disbursement->{$method}($this->fields());

        $transaction = $this->requestBody($client)['transaction'];
        $this->assertSame($code, $transaction['providerCode']);
        $this->assertSame($name, $transaction['providerName']);
    }

    public static function payoutProviders(): array
    {
        return [
            ['ecocash', 'EcoCash', 'EC'],
            ['onemoney', 'OneMoney', 'OM'],
            ['omari', 'Omari', 'OC'],
            ['innbucks', 'InnBucks', 'IB'],
            ['mtn', 'MTN', 'MN'],
            ['airtel', 'AirTel', 'AT'],
            ['zamtel', 'ZamTel', 'ZT'],
            ['mpesa', 'MPesa', 'MP'],
            ['voucher', 'Voucher', 'VC'],
        ];
    }

    public function test_voucher_payout_rejects_unsupported_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Currency 'GBP' is not supported by provider 'voucher'");

        $this->disbursement()->voucher($this->fields(['currency' => 'GBP']));
    }

    /**
     * @dataProvider acquireOnlyProviders
     */
    public function test_acquire_only_providers_cannot_be_paid_out(string $method): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Provider '{$method}' does not support payouts.");

        $this->disbursement()->{$method}($this->fields());
    }

    public static function acquireOnlyProviders(): array
    {
        return [['telecash'], ['equitel'], ['orange'], ['tigo']];
    }

    public function test_country_code_defaults_to_zw_and_can_be_overridden(): void
    {
        $disbursement = $this->disbursement();
        $client = $this->fake($disbursement, [$this->ok(), $this->ok()]);

        $disbursement->ecocash($this->fields());
        $this->assertSame('ZW', $this->requestBody($client)['customer']['countryCode']);

        $disbursement->mpesa($this->fields(['countryCode' => 'KE']));
        $this->assertSame('KE', $this->requestBody($client)['customer']['countryCode']);
    }

    public function test_private_key_can_be_loaded_from_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'key');
        file_put_contents($path, $this->keys['private']);

        try {
            $disbursement = (new Disbursement('test-token', 'test-secret', $path))->setMerchantId(116);
            $client = $this->fake($disbursement, [$this->ok()]);

            $disbursement->ecocash($this->fields());

            $this->assertNotSame('', $client->lastRequest()->getHeaderLine('checksum'));
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_private_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Disbursement('test-token', 'test-secret', 'not a key');
    }

    public function test_missing_merchant_id_throws(): void
    {
        $disbursement = new Disbursement('test-token', 'test-secret', $this->keys['private']);
        $client = $this->fake($disbursement);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setMerchantId()');

        try {
            $disbursement->ecocash($this->fields());
        } finally {
            $this->assertSame([], $client->history);
        }
    }

    public function test_non_numeric_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount');

        $this->disbursement()->ecocash($this->fields(['amount' => 'ten']));
    }

    public function test_missing_reference_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: reference');

        $this->disbursement()->ecocash($this->fields(['reference' => '']));
    }
}
