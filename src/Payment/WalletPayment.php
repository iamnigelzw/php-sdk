<?php

namespace ContiPay\PhpSdk\Payment;

use ContiPay\PhpSdk\Core\Client as Core;
use ContiPay\PhpSdk\Payload\PayloadGenerator;
use ContiPay\PhpSdk\Concerns\HasStatus;
use ContiPay\PhpSdk\Support\Amount;
use ContiPay\PhpSdk\Support\ProviderRegistry;

/**
 * Shared behaviour for wallet-style acquiring classes.
 *
 * Subclasses only declare which provider type they route to; everything else —
 * URLs, merchant ID, payload building and dispatch — lives here.
 */
abstract class WalletPayment
{
    use HasStatus;

    /**
     * Contipay instance
     * @var Core|null
     */
    protected ?Core $contipay;
    /**
     * Environment mode of the SDK
     * @var string
     */
    protected string $mode = 'dev';
    /**
     * Webhook URL of the Contipay instance
     * @var string
     */
    protected string $webhookUrl = '';
    /**
     * Success URL of the Contipay instance
     * @var string
     */
    protected string $successUrl = '';
    /**
     * Error URL of the Contipay instance
     * @var string
     */
    protected string $errorUrl = '';
    /**
     * Merchant ID of the Contipay instance
     * @var int
     */
    protected int $merchantId;
    /**
     * Payment processing method
     * @var string
     */
    protected string $method;

    /**
     * The provider type this class routes to.
     *
     * @return string One of the ProviderRegistry::TYPE_* constants
     */
    abstract protected function providerType(): string;

    /**
     * Providers this class can collect payments through, keyed by method name.
     *
     * @return array<string, array{name: string, code: string, type: string, capabilities: list<string>, required: list<string>, currencies: list<string>|null}>
     */
    public function providers(): array
    {
        return ProviderRegistry::supporting(ProviderRegistry::ACQUIRE, $this->providerType());
    }


    /**
     * Initialize the Contipay instance with API credentials.
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        ?string $mode = null,
        ?string $method = null
    ) {
        $this->contipay = new Core($apiKey, $apiSecret);
        $this->mode = $mode ?? 'dev';
        $this->method = $method ?? 'direct';
    }

    /**
     * Make payments
     * 
     * @param string $name
     * @param array $arguments
     * @throws \BadMethodCallException
     * @return string
     */
    public function __call(string $name, array $arguments): string
    {
        $provider = ProviderRegistry::find($name);

        if ($provider === null || $provider['type'] !== $this->providerType()) {
            throw new \BadMethodCallException("Provider '{$name}' not supported.");
        }

        if (!ProviderRegistry::supports($name, ProviderRegistry::ACQUIRE)) {
            throw new \BadMethodCallException("Provider '{$name}' does not support payments.");
        }

        $fields = $arguments[0] ?? [];

        foreach ($provider['required'] as $field) {
            if (empty($fields[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field} for provider '{$name}'");
            }
        }

        if (!ProviderRegistry::supportsCurrency($name, (string) $fields['currency'])) {
            throw new \InvalidArgumentException(sprintf(
                "Currency '%s' is not supported by provider '%s'. Supported: %s.",
                $fields['currency'],
                $name,
                implode(', ', $provider['currencies'])
            ));
        }

        Amount::parse($fields['amount']);

        return $this->processPayment(
            $fields['amount'] ?? '0',
            $fields['currency'] ?? 'USD',
            $fields['phone'] ?? '',
            $fields['reference'] ?? '',
            $fields['description'] ?? 'Payment',
            $provider['name'] == '' ? $fields['provider'] : $provider['name'],
            $provider['code'] == '' ? $fields['code'] : $provider['code'],
            $fields['countryCode'] ?? 'ZW'
        );
    }


    /**
     * Update the Contipay API URLs for dev/live environments.
     *
     * @param string $dev  Development API URL
     * @param string $live Live API URL
     * @return $this
     */
    public function updateContipayURL(string $dev = 'https://api-uat.contipay.net', string $live = 'https://api.contipay.net'): self
    {
        $this->contipay->updateURL($dev, $live);
        return $this;
    }

    /**
     * Set the webhook URL for payment notifications.
     *
     * @param string $webhookUrl
     * @return $this
     */
    public function setWebhookUrl(string $webhookUrl = ''): self
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }

    /**
     * Get the webhook URL for payment notifications.
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    /**
     * Set the success URL for payment redirection.
     *
     * @param string $successUrl
     * @return $this
     */
    public function setSuccessUrl(string $successUrl = ''): self
    {
        $this->successUrl = $successUrl;
        return $this;
    }

    /**
     * Get the success URL for payment redirection.
     *
     * @return string
     */
    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    /**
     * Set the error URL for payment redirection.
     *
     * @param string $errorUrl
     * @return $this
     */
    public function setErrorUrl(string $errorUrl = ''): self
    {
        $this->errorUrl = $errorUrl;
        return $this;
    }

    /**
     * Get the error URL for payment redirection.
     *
     * @return string
     */
    public function getErrorUrl(): string
    {
        return $this->errorUrl;
    }


    /**
     * Set the merchant ID for this Contipay instance.
     *
     * @param int $merchantId
     * @return $this
     */
    public function setMerchantId(int $merchantId = 1): self
    {
        $this->merchantId = $merchantId;
        return $this;
    }

    /**
     * Get the merchant ID.
     *
     * @throws \LogicException If no merchant ID has been set
     * @return int
     */
    public function getMerchantId(): int
    {
        if (!isset($this->merchantId)) {
            throw new \LogicException('Call setMerchantId() before making a request.');
        }

        return $this->merchantId;
    }

    /**
     * Process a basic payment (minimal required fields).
     *
     * @param string $amount      Payment amount
     * @param string $currency    Payment currency code
     * @param string $phone       Payer phone number
     * @param string $reference   Payment reference (optional)
     * @param string $description Payment description (optional)
     * @param string $providerName Provider name
     * @param string $providerCode Provider code
     * @param string $countryCode  Payer country code (optional)
     * @throws \LogicException If no merchant ID has been set
     * @return string JSON-encoded payment response or error
     */
    public function processPayment(
        string $amount,
        string $currency,
        string $phone,
        string $reference = '',
        string $description = 'Donation',
        string $providerName = 'EcoCash',
        string $providerCode = 'EC',
        string $countryCode = 'ZW'
    ): string {
        $merchantId = $this->getMerchantId();

        try {
            $amount = Amount::parse($amount);

            $payload = $this->method == 'direct' ? (new PayloadGenerator(
                $merchantId,
                $this->getWebhookUrl(),
            ))
                ->setUpProviders($providerName, $providerCode)
                ->simpleDirectPayload(
                    $amount,
                    $phone,
                    $currency,
                    $reference,
                    $description,
                    '',
                    $countryCode
                ) :
                ((new PayloadGenerator(
                    $merchantId,
                    $this->getWebhookUrl(),
                    $this->getSuccessUrl(),
                    $this->getErrorUrl()
                ))->simpleRedirectPayload(
                        $amount,
                        $phone,
                        $currency,
                        $reference,
                        $description,
                        '',
                        false,
                        false,
                        $countryCode
                    ));

            $response = $this->contipay
                ->setAppMode($this->mode)
                ->setPaymentMethod($this->method)
                ->process($payload);

            return $response;
        } catch (\Throwable $th) {
            return json_encode([
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }
    }

}