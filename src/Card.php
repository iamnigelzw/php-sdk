<?php

namespace ContiPay\PhpSdk;

use Contipay\Core\Contipay as Core;
use Contipay\Helpers\Payload\PayloadGenerator;

class Card
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
        'visa' => [
            'name' => 'Visa',
            'code' => 'VA',
            'required' => ['amount', 'currency', 'phone', 'accountNumber', 'accountExpiry', 'cvv', 'reference'],
        ],
        'mastercard' => [
            'name' => 'MasterCard',
            'code' => 'MA',
            'required' => ['amount', 'currency', 'phone', 'accountNumber', 'accountExpiry', 'cvv'],
        ],
        'zimswitch' => [
            'name' => 'ZimSwitch',
            'code' => 'ZS',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'card' => [
            'name' => '',
            'code' => '',
            'required' => ['amount', 'currency', 'phone', 'accountNumber', 'accountExpiry', 'cvv', 'provider', 'code'],
        ],
    ];



    /**
     * Initialize the Contipay instance with API credentials.
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        ?string $mode = null,
        ?string $method = 'direct'
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
        if (!isset($this->providers[$name])) {
            throw new \BadMethodCallException("Provider '{$name}' not supported.");
        }

        $provider = $this->providers[$name];
        $fields = $arguments[0] ?? [];

        foreach ($provider['required'] as $field) {
            if (empty($fields[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field} for provider '{$name}'");
            }
        }

        return $this->processPayment(
            $fields['firstName'] ?? '',
            $fields['lastName'] ?? '',
            $fields['accountNumber'] ?? '',
            $fields['phone'] ?? '',
            $fields['email'] ?? '',
            $fields['amount'] ?? '0',
            $fields['currency'] ?? 'USD',
            $fields['accountExpiry'] ?? '',
            $fields['cvv'] ?? '',
            $fields['country'] ?? 'ZW',
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
     * Process a full direct payment with complete customer and transaction details.
     *
     * @param string $firstName   Customer first name
     * @param string $lastName    Customer last name
     * @param string $accountNumber Customer account number
     * @param string $phone       Customer phone number
     * @param string $email       Customer email address
     * @param string $amount      Payment amount
     * @param string $currency    Payment currency code
     * @param string $accountExpiry Customer account expiry date
     * @param string $cvv         Customer CVV
     * @param string $country     Customer country code (default: 'ZW')
     * @param string $reference   Payment reference (optional)
     * @param string $description Payment description (optional)
     * @return string JSON-encoded payment response or error    
     */
    public function processPayment(
        string $firstName,
        string $lastName,
        string $accountNumber,
        string $phone,
        string $email,
        string $amount,
        string $currency,
        string $accountExpiry = '',
        string $cvv = '',
        string $country = 'ZW',
        string $reference = '',
        string $description = 'Donation',
        string $providerName = 'EcoCash',
        string $providerCode = 'EC',
    ): string {
        $payload = $this->method == 'direct' ? (
            new PayloadGenerator(
                $this->getMerchantId(),
                $this->getWebhookUrl()
            )
        )->setUpCustomer($firstName, $lastName, $phone, $country, $email)
            ->setUpProviders($providerName, $providerCode)
            ->setUpAccountDetails($accountNumber ?: $phone, "$firstName $lastName", $accountExpiry, $cvv)
            ->setUpTransaction($amount, $currency, $reference, $description)
            ->directPayload()

            : (
                new PayloadGenerator(
                    $this->getMerchantId(),
                    $this->getWebhookUrl(),
                    $this->getSuccessUrl(),
                    $this->getErrorUrl()
                )
            )->setUpCustomer($firstName, $lastName, $phone, $country, $email)
                ->setUpTransaction($amount, $currency)
                ->redirectPayload();
        ;

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