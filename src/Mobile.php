<?php

namespace ContiPay\PhpSdk;

use Contipay\Core\Contipay as Core;
use Contipay\Helpers\Payload\PayloadGenerator;

class Mobile
{
    /**
     * Contipay instance
     * @var Core|null
     */
    protected ?Core $contipay;
    /**
     * Environment mode of the SDK
     * @var 
     */
    protected string $mode = 'dev';
    /**
     * Webhook URL of the Contipay instance
     * @var string
     */
    protected string $webhookUrl;
    /**
     * Success URL of the Contipay instance
     * @var string
     */
    /**
     * Success URL of the Contipay instance
     * @var string
     */
    protected string $successUrl;
    /**
     * Error URL of the Contipay instance
     * @var string
     */
    protected string $errorUrl;
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
     * Array of supported providers
     * @var array
     */
    protected array $providers = [
        'ecocash' => [
            'name' => 'EcoCash',
            'code' => 'EC',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'onemoney' => [
            'name' => 'OneMoney',
            'code' => 'OM',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'omari' => [
            'name' => 'Omari',
            'code' => 'OC',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'innbucks' => [
            'name' => 'Omari',
            'code' => 'OC',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'mobile' => [
            'name' => '',
            'code' => '',
            'required' => ['amount', 'currency', 'phone', 'reference', 'provider', 'code'],
        ],
    ];


    /**
     * Initialize the Contipay instance with API credentials.
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        ?string $mode = null,
        ?string $method = null,
    ) {
        $this->contipay = new Core($apiKey, $apiSecret);
        $this->mode = $mode ?? 'dev';
        $this->method = $method ?? 'direct';

        // force update to new Contipay
        $this->updateContipayURL();
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
        if (!isset($this->providers[$name])) {
            throw new \BadMethodCallException("Provider '{$name}' not supported.");
        }

        $fields = $arguments[0] ?? [];
        $provider = $this->providers[$name];

        foreach ($provider['required'] as $field) {
            if (empty($fields[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field} for provider '{$name}'");
            }
        }

        return $this->processPayment(
            $fields['amount'] ?? '0',
            $fields['currency'] ?? 'USD',
            $fields['phone'] ?? '',
            $fields['reference'] ?? '',
            $fields['description'] ?? 'Payment',
            $provider['name'] == '' ? $fields['provider'] : $provider['name'],
            $provider['code'] == '' ? $fields['code'] : $provider['code']
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
     * @return int
     */
    public function getMerchantId(): int
    {
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
    ): string {

        $payload = $this->method == 'direct' ? (new PayloadGenerator(
            $this->getMerchantId(),
            $this->getWebhookUrl(),
        ))
            ->setUpProviders($providerName, $providerCode)
            ->simpleDirectPayload(
                $amount,
                $phone,
                $currency,
                $reference,
                $description
            ) :
            (new PayloadGenerator(
                $this->getMerchantId(),
                $this->getWebhookUrl(),
                $this->getSuccessUrl(),
                $this->getErrorUrl()
            )->simpleRedirectPayload(
                    $amount,
                    $phone
                ));


        try {
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