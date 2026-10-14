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
