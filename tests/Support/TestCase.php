<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests\Support;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Swap the SDK object's core client for a fake that returns the given responses.
     */
    protected function fake(object $sdk, array $responses = []): FakeClient
    {
        $client = new FakeClient('test-token', 'test-secret', $responses);
        (new \ReflectionProperty($sdk, 'contipay'))->setValue($sdk, $client);

        return $client;
    }

    protected function ok(array $body = ['status' => 'Success']): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    protected function decode(string $json): array
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function requestBody(FakeClient $client): array
    {
        return $this->decode((string) $client->lastRequest()->getBody());
    }

    /**
     * @return array{private: string, public: string}
     */
    protected function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);

        return ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }
}
