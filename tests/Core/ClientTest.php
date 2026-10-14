<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests\Core;

use ContiPay\PhpSdk\Core\Client;
use ContiPay\PhpSdk\Tests\Support\FakeClient;
use ContiPay\PhpSdk\Tests\Support\TestCase;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    public function test_mode_selects_base_url(): void
    {
        $client = new Client('token', 'secret');

        $this->assertSame(Client::DEFAULT_UAT_URL, $client->getBaseURL());
        $this->assertSame(Client::DEFAULT_LIVE_URL, $client->setAppMode('live')->getBaseURL());
        $this->assertSame(Client::MODE_LIVE, $client->getAppMode());
    }

    public function test_invalid_mode_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Client('token', 'secret'))->setAppMode('prod');
    }

    public function test_update_url_overrides_both_environments(): void
    {
        $client = (new Client('token', 'secret'))->updateURL('https://dev.test', 'https://live.test');

        $this->assertSame('https://dev.test', $client->getBaseURL());
        $this->assertSame('https://live.test', $client->setAppMode('live')->getBaseURL());
    }

    public function test_json_error_body_is_passed_through(): void
    {
        $client = new FakeClient(responses: [
            new Response(400, [], json_encode(['status' => 'Failed', 'message' => 'Invalid merchant'])),
        ]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame(['status' => 'Failed', 'message' => 'Invalid merchant'], $result);
    }

    public function test_non_json_error_body_becomes_json_error(): void
    {
        $client = new FakeClient(responses: [new Response(502, [], '<html>Bad Gateway</html>')]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('502', $result['message']);
    }

    public function test_checksum_signs_fields_in_order(): void
    {
        $keys = $this->keyPair();
        $payload = [
            'transaction' => ['reference' => 'REF-9', 'merchantId' => 42, 'amount' => 10.5],
            'accountDetails' => ['accountNumber' => '0771111111'],
        ];

        $checksum = (new Client('token', 'secret'))->generateChecksum($payload, $keys['private']);

        $signed = 'token' . 'REF-9' . '42' . '0771111111' . '10.5';
        $this->assertSame(1, openssl_verify($signed, base64_decode($checksum), $keys['public'], OPENSSL_ALGO_SHA256));
    }

    public function test_checksum_with_bad_key_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        (new Client('token', 'secret'))->generateChecksum([], 'not a key');
    }
}
