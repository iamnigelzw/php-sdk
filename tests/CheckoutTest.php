<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Checkout;
use ContiPay\PhpSdk\Tests\Support\TestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class CheckoutTest extends TestCase
{
    private const PAGE_URL = 'https://acquire-uat.contipay.net/payment/pay?token=abc-123';

    private function checkout(string $mode = 'dev'): Checkout
    {
        return (new Checkout('test-token', 'test-secret', $mode))
            ->setMerchantId(116)
            ->setWebhookUrl('https://shop.test/webhook')
            ->setSuccessUrl('https://shop.test/success')
            ->setCancelUrl('https://shop.test/cancel');
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'amount' => '50.00',
            'currency' => 'USD',
            'phone' => '0782000340',
            'reference' => 'ORDER-1',
        ];
    }

    private function pending(): Response
    {
        return $this->ok([
            'status' => 'Pending',
            'statusCode' => 0,
            'message' => 'Transaction Request Succesful',
            'redirectUrl' => self::PAGE_URL,
            'mode' => 'response',
        ]);
    }

    public function test_create_returns_hosted_page_url(): void
    {
        $checkout = $this->checkout();
        $this->fake($checkout, [$this->pending()]);

        $session = $checkout->create($this->fields());

        $this->assertTrue($session->successful());
        $this->assertSame(self::PAGE_URL, $session->url());
        $this->assertSame('ORDER-1', $session->reference());
        $this->assertSame('Transaction Request Succesful', $session->message());
        $this->assertSame('Pending', $session->toArray()['status']);
    }

    public function test_create_sends_put_with_redirect_payload(): void
    {
        $checkout = $this->checkout();
        $client = $this->fake($checkout, [$this->pending()]);

        $checkout->create($this->fields([
            'description' => 'Order #1',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'cod' => true,
        ]));

        $request = $client->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('https://api-uat.contipay.net/acquire/payment', (string) $request->getUri());

        $body = $this->requestBody($client);
        $this->assertSame('ORDER-1', $body['reference']);
        $this->assertSame('Order #1', $body['description']);
        $this->assertEquals(50.0, $body['amount']);
        $this->assertSame('USD', $body['currencyCode']);
        $this->assertSame(116, $body['merchantId']);
        $this->assertTrue($body['cod']);
        $this->assertFalse($body['coc']);
        $this->assertSame('https://shop.test/webhook', $body['webhookUrl']);
        $this->assertSame('https://shop.test/success', $body['successUrl']);
        $this->assertSame('https://shop.test/cancel', $body['cancelUrl']);
        $this->assertSame('John', $body['customer']['firstName']);
        $this->assertSame('Doe', $body['customer']['surname']);
        $this->assertSame('john@example.com', $body['customer']['email']);
        $this->assertSame('0782000340', $body['customer']['cell']);
        $this->assertArrayNotHasKey('transaction', $body);
        $this->assertArrayNotHasKey('accountDetails', $body);
    }

    public function test_customer_defaults_to_phone(): void
    {
        $checkout = $this->checkout();
        $client = $this->fake($checkout, [$this->pending()]);

        $checkout->create($this->fields());

        $customer = $this->requestBody($client)['customer'];
        $this->assertSame('0782000340', $customer['firstName']);
        $this->assertSame('0782000340', $customer['surname']);
        $this->assertSame('ZW', $customer['countryCode']);
    }

    public function test_missing_reference_is_generated(): void
    {
        $checkout = $this->checkout();
        $client = $this->fake($checkout, [$this->pending()]);

        $session = $checkout->create($this->fields(['reference' => '']));

        $this->assertMatchesRegularExpression('/^V-\d{8}$/', $session->reference());
        $this->assertSame($session->reference(), $this->requestBody($client)['reference']);
    }

    public function test_live_mode_uses_live_url(): void
    {
        $checkout = $this->checkout('live');
        $client = $this->fake($checkout, [$this->pending()]);

        $checkout->create($this->fields());

        $this->assertSame('api.contipay.net', $client->lastRequest()->getUri()->getHost());
    }

    public function test_rejection_has_no_url(): void
    {
        $checkout = $this->checkout();
        $this->fake($checkout, [$this->ok(['status' => 'Failed', 'message' => 'Invalid merchant'])]);

        $session = $checkout->create($this->fields());

        $this->assertFalse($session->successful());
        $this->assertNull($session->url());
        $this->assertSame('Invalid merchant', $session->message());
        $this->assertSame('ORDER-1', $session->reference());
    }

    public function test_connection_failure_has_no_url(): void
    {
        $checkout = $this->checkout();
        $this->fake($checkout, [new ConnectException('Connection refused', new Request('PUT', '/'))]);

        $session = $checkout->create($this->fields());

        $this->assertFalse($session->successful());
        $this->assertSame(['status' => 'error', 'message' => 'Connection refused'], $session->toArray());
    }

    public function test_missing_merchant_id_throws(): void
    {
        $checkout = new Checkout('test-token', 'test-secret');
        $client = $this->fake($checkout);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setMerchantId()');

        try {
            $checkout->create($this->fields());
        } finally {
            $this->assertSame([], $client->history);
        }
    }

    public function test_missing_required_field_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: phone');

        $this->checkout()->create($this->fields(['phone' => '']));
    }

    public function test_non_numeric_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid amount: 'abc'");

        $this->checkout()->create($this->fields(['amount' => 'abc']));
    }

    public function test_transaction_status_uses_acquire_endpoint(): void
    {
        $checkout = $this->checkout();
        $client = $this->fake($checkout, [$this->ok(['status' => 'Paid'])]);

        $result = $this->decode($checkout->getTransactionStatus('ORDER-1'));

        $this->assertSame(['status' => 'Paid'], $result);
        $this->assertStringContainsString('ORDER-1', (string) $client->lastRequest()->getUri());
    }
}
