<?php

namespace ContiPay\PhpSdk;

use ContiPay\PhpSdk\Core\Client as Core;
use Contipay\Helpers\Payload\PayloadGenerator;
use ContiPay\PhpSdk\Concerns\HasStatus;

class Disbursement
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
     * PEM-encoded private key used to sign disbursements
     * @var string
     */
    protected string $privateKey;
    /**
     * Webhook URL of the Contipay instance
     * @var string
     */
    protected string $webhookUrl = '';
    /**
     * Merchant ID of the Contipay instance
     * @var int
     */
    protected int $merchantId;

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
            'name' => 'InnBucks',
            'code' => 'IB',
            'required' => ['amount', 'currency', 'phone', 'reference'],
        ],
        'mobile' => [
            'name' => '',
            'code' => '',
            'required' => ['amount', 'currency', 'phone', 'reference', 'provider', 'code'],
        ],
    ];

    /**
     * Initialize the Contipay instance with API credentials and signing key.
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $privateKey PEM-encoded private key, or a path to a PEM file
     * @param string|null $mode  'dev' or 'live'
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        string $privateKey,
        ?string $mode = null
    ) {
        $this->contipay = new Core($apiKey, $apiSecret);
        $this->privateKey = $this->loadPrivateKey($privateKey);
        $this->mode = $mode ?? 'dev';
    }

    /**
     * Make disbursements
     *
     * @param string $name
     * @param array $arguments
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
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

        return $this->processDisbursement(
            $fields['amount'],
            $fields['currency'],
            $fields['phone'],
            $fields['reference'],
            $fields['description'] ?? 'Disbursement',
            $provider['name'] == '' ? $fields['provider'] : $provider['name'],
            $provider['code'] == '' ? $fields['code'] : $provider['code'],
            $fields['accountName'] ?? '-',
            $fields['firstName'] ?? '-',
            $fields['lastName'] ?? '-',
            $fields['email'] ?? '',
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
     * Set the webhook URL for disbursement notifications.
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
     * Get the webhook URL for disbursement notifications.
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
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
     * Send money to a mobile wallet.
     *
     * @param string $amount       Disbursement amount
     * @param string $currency     Currency code
     * @param string $phone        Recipient phone / wallet number
     * @param string $reference    Disbursement reference
     * @param string $description  Disbursement description
     * @param string $providerName Provider name
     * @param string $providerCode Provider code
     * @param string $accountName  Recipient account name
     * @param string $firstName    Recipient first name
     * @param string $lastName     Recipient last name
     * @param string $email        Recipient email
     * @return string JSON-encoded disbursement response or error
     */
    public function processDisbursement(
        string $amount,
        string $currency,
        string $phone,
        string $reference,
        string $description = 'Disbursement',
        string $providerName = 'EcoCash',
        string $providerCode = 'EC',
        string $accountName = '-',
        string $firstName = '-',
        string $lastName = '-',
        string $email = ''
    ): string {
        try {
            $payload = (new PayloadGenerator(
                $this->getMerchantId(),
                $this->getWebhookUrl(),
            ))
                ->setUpCustomer($firstName, $lastName, $phone, 'ZW', $email)
                ->setUpProviders($providerName, $providerCode)
                ->setUpAccountDetails($phone, $accountName)
                ->setUpTransaction((float) $amount, $currency, $reference, $description)
                ->directPayload();

            return $this->contipay
                ->setAppMode($this->mode)
                ->setPaymentMethod('direct')
                ->disburse($payload, $this->privateKey);
        } catch (\Throwable $th) {
            return json_encode([
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }
    }

    /**
     * Resolve and validate the private key, accepting a PEM string or a file path.
     *
     * @param string $privateKey
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function loadPrivateKey(string $privateKey): string
    {
        if (strpos($privateKey, '-----BEGIN') === false && is_file($privateKey)) {
            $privateKey = (string) file_get_contents($privateKey);
        }

        if (openssl_pkey_get_private($privateKey) === false) {
            throw new \InvalidArgumentException('Invalid private key: provide a PEM-encoded key or a path to one.');
        }

        return $privateKey;
    }
}
