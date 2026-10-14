<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests\Core;

use ContiPay\PhpSdk\Core\Client;
use ContiPay\PhpSdk\Tests\Support\FakeClient;
use ContiPay\PhpSdk\Tests\Support\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
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
        $this->assertSame(Client::OUTCOME_UNKNOWN, $result['outcome']);
    }

    public function test_non_json_client_error_is_a_definite_failure(): void
    {
        $client = new FakeClient(responses: [new Response(404, [], '<html>Not Found</html>')]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame(Client::OUTCOME_FAILED, $result['outcome']);
    }

    public function test_connection_refused_is_a_definite_failure(): void
    {
        $client = new FakeClient(responses: [
            new ConnectException('Connection refused', new Request('POST', '/'), null, ['errno' => 7]),
        ]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame(['status' => 'error', 'message' => 'Connection refused', 'outcome' => Client::OUTCOME_FAILED], $result);
    }

    public function test_timeout_after_sending_is_unknown(): void
    {
        // Guzzle raises ConnectException for operation timeouts (cURL error 28) too.
        $client = new FakeClient(responses: [
            new ConnectException('Operation timed out', new Request('POST', '/'), null, ['errno' => 28]),
        ]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame(Client::OUTCOME_UNKNOWN, $result['outcome']);
    }

    public function test_request_error_without_response_is_unknown(): void
    {
        $client = new FakeClient(responses: [
            new RequestException('Connection reset', new Request('POST', '/')),
        ]);

        $result = $this->decode($client->process(['amount' => 1]));

        $this->assertSame(Client::OUTCOME_UNKNOWN, $result['outcome']);
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
