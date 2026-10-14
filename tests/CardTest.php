<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests;

use ContiPay\PhpSdk\Card;
use ContiPay\PhpSdk\Tests\Support\TestCase;

class CardTest extends TestCase
{
    private function card(?string $method = null): Card
    {
        return (new Card('test-token', 'test-secret', 'dev', $method))
            ->setMerchantId(116)
            ->setWebhookUrl('https://shop.test/webhook');
    }

    private function fields(array $overrides = []): array
    {
        return $overrides + [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'accountNumber' => '4111111111111111',
            'accountExpiry' => '12/26',
            'cvv' => '123',
            'phone' => '0782000340',
            'email' => 'john@example.com',
            'amount' => '25.50',
            'currency' => 'USD',
            'reference' => 'CARD-1',
        ];
    }

    public function test_direct_visa_payment_sends_card_details(): void
    {
        $card = $this->card();
        $client = $this->fake($card, [$this->ok()]);

        $card->visa($this->fields());

        $this->assertSame('POST', $client->lastRequest()->getMethod());
        $body = $this->requestBody($client);
        $this->assertSame('VA', $body['transaction']['providerCode']);
        $this->assertEquals(25.5, $body['transaction']['amount']);
        $this->assertSame('CARD-1', $body['transaction']['reference']);
        $this->assertSame('4111111111111111', $body['accountDetails']['accountNumber']);
        $this->assertSame('John Doe', $body['accountDetails']['accountName']);
        $this->assertSame('12/26', $body['accountDetails']['accountExtra']['expiry']);
        $this->assertSame('123', $body['accountDetails']['accountExtra']['cvv']);
        $this->assertSame('john@example.com', $body['customer']['email']);
    }

    public function test_redirect_payment_omits_card_details(): void
    {
        $card = $this->card('redirect')->setSuccessUrl('https://shop.test/success');
        $client = $this->fake($card, [$this->ok()]);

        $card->visa($this->fields());

        $this->assertSame('PUT', $client->lastRequest()->getMethod());
        $body = $this->requestBody($client);
        $this->assertArrayNotHasKey('accountDetails', $body);
        $this->assertSame('https://shop.test/success', $body['successUrl']);
    }

    public function test_missing_merchant_id_throws(): void
    {
        $card = new Card('test-token', 'test-secret');
        $this->fake($card);

        $this->expectException(\LogicException::class);

        $card->visa($this->fields());
    }

    public function test_non_numeric_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid amount');

        $this->card()->visa($this->fields(['amount' => '12abc']));
    }

    public function test_missing_cvv_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: cvv');

        $this->card()->mastercard($this->fields(['cvv' => '']));
    }
}
