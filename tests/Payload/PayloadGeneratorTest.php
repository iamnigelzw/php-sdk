<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests\Payload;

use ContiPay\PhpSdk\Payload\PayloadGenerator;
use ContiPay\PhpSdk\Support\Reference;
use PHPUnit\Framework\TestCase;

class PayloadGeneratorTest extends TestCase
{
    public function test_direct_payload_shape(): void
    {
        $payload = (new PayloadGenerator(116, 'https://shop.test/webhook'))
            ->setUpCustomer('Jane', 'Doe', '0771234567', 'ZW', 'jane@example.com')
            ->setUpProviders('OneMoney', 'OM')
            ->setUpAccountDetails()
            ->setUpTransaction(12.5, 'USD', 'REF-1', 'Order 1')
            ->directPayload();

        $this->assertSame([
            'nationalId' => '-',
            'firstName' => 'Jane',
            'middleName' => '-',
            'surname' => 'Doe',
            'email' => 'jane@example.com',
            'cell' => '0771234567',
            'countryCode' => 'ZW',
        ], $payload['customer']);
        $this->assertSame('OM', $payload['transaction']['providerCode']);
        $this->assertSame(12.5, $payload['transaction']['amount']);
        $this->assertSame('USD', $payload['transaction']['currencyCode']);
        $this->assertSame('Order 1', $payload['transaction']['description']);
        $this->assertSame(116, $payload['transaction']['merchantId']);
        $this->assertSame('REF-1', $payload['transaction']['reference']);
        $this->assertSame('0771234567', $payload['accountDetails']['accountNumber'], 'account defaults to the cell number');
    }

    public function test_email_defaults_from_cell(): void
    {
        $payload = (new PayloadGenerator(1, ''))
            ->setUpCustomer('Jane', 'Doe', '0771234567')
            ->setUpTransaction(1)
            ->redirectPayload();

        $this->assertSame('0771234567@contipay.co.zw', $payload['customer']['email']);
    }

    public function test_reference_and_description_are_generated_when_empty(): void
    {
        $payload = (new PayloadGenerator(1, ''))
            ->setUpCustomer('Jane', 'Doe', '0771234567')
            ->setUpTransaction(1)
            ->redirectPayload();

        $this->assertMatchesRegularExpression('/^V-\d{8}$/', $payload['reference']);
        $this->assertSame('Payment with ref:' . $payload['reference'], $payload['description']);
    }

    public function test_redirect_payload_includes_redirect_urls_and_flags(): void
    {
        $payload = (new PayloadGenerator(1, 'https://w.test', 'https://s.test', 'https://c.test'))
            ->setUpCustomer('Jane', 'Doe', '0771234567')
            ->setUpTransaction(5, 'USD', 'REF-2')
            ->redirectPayload(true, false);

        $this->assertTrue($payload['coc']);
        $this->assertFalse($payload['cod']);
        $this->assertSame('https://s.test', $payload['successUrl']);
        $this->assertSame('https://c.test', $payload['cancelUrl']);
        $this->assertSame('https://w.test', $payload['webhookUrl']);
    }

    public function test_simple_direct_payload_uses_account_as_cell(): void
    {
        $payload = (new PayloadGenerator(1, ''))
            ->setUpProviders('EcoCash', 'EC')
            ->simpleDirectPayload(3, '0779999999', 'USD', 'REF-3');

        $this->assertSame('0779999999', $payload['customer']['cell']);
        $this->assertSame('0779999999', $payload['accountDetails']['accountExtra']['smsNumber']);
        $this->assertSame('REF-3', $payload['transaction']['reference']);
    }

    public function test_direct_payload_requires_setup(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setUpProviders()');

        (new PayloadGenerator(1, ''))
            ->setUpCustomer('Jane', 'Doe', '0771234567')
            ->setUpAccountDetails()
            ->setUpTransaction(1)
            ->directPayload();
    }

    public function test_simple_direct_payload_requires_provider(): void
    {
        $this->expectException(\LogicException::class);

        (new PayloadGenerator(1, ''))->simpleDirectPayload(1, '0771234567');
    }

    public function test_reference_is_zero_padded(): void
    {
        $this->assertSame('000042', (new Reference())->generate(6, 42));
        $this->assertMatchesRegularExpression('/^\d{10}$/', (new Reference())->generate(10));
    }
}
