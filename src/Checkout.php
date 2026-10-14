<?php

namespace ContiPay\PhpSdk;

use ContiPay\PhpSdk\Core\Client as Core;
use ContiPay\PhpSdk\Payload\PayloadGenerator;
use ContiPay\PhpSdk\Concerns\HasStatus;

/**
 * Hosted checkout: send the customer to ContiPay's payment page,
 * where they choose how to pay.
 */
class Checkout
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
     * URL the customer returns to after paying
     * @var string
     */
    protected string $successUrl = '';
    /**
     * URL the customer returns to after cancelling
     * @var string
     */
    protected string $cancelUrl = '';
    /**
     * Merchant ID of the Contipay instance
     * @var int
     */
    protected int $merchantId;

    /**
     * Initialize the Contipay instance with API credentials.
     */
    public function __construct(string $apiKey, string $apiSecret, ?string $mode = null)
    {
        $this->contipay = new Core($apiKey, $apiSecret);
        $this->mode = $mode ?? 'dev';
    }

    /**
     * Update the Contipay API URLs for dev/live environments.
     *
     * @param string $dev  Development API URL
     * @param string $live Live API URL
     * @return $this
     */
    public function updateContipayURL(string $dev = Core::DEFAULT_UAT_URL, string $live = Core::DEFAULT_LIVE_URL): self
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
     * Set the URL the customer returns to after paying.
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
     * Get the URL the customer returns to after paying.
     *
     * @return string
     */
    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    /**
     * Set the URL the customer returns to after cancelling.
     *
     * @param string $cancelUrl
     * @return $this
     */
    public function setCancelUrl(string $cancelUrl = ''): self
    {
        $this->cancelUrl = $cancelUrl;
        return $this;
    }

    /**
     * Get the URL the customer returns to after cancelling.
     *
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
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
     * Create a checkout session on ContiPay's hosted payment page.
     *
     * Required fields: amount, currency, phone.
     * Optional fields: reference (generated when empty), description,
     * firstName, lastName, email, country, cod, coc.
     *
     * @param array $fields
     * @throws \LogicException If no merchant ID has been set
     * @throws \InvalidArgumentException If a required field is missing or amount is not a number
     * @return CheckoutSession
     */
    public function create(array $fields): CheckoutSession
    {
        $merchantId = $this->getMerchantId();

        foreach (['amount', 'currency', 'phone'] as $field) {
            if (empty($fields[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field} for checkout");
            }
        }

        if (!is_numeric($fields['amount'])) {
            throw new \InvalidArgumentException("Invalid amount: '{$fields['amount']}' is not a number");
        }

        $phone = (string) $fields['phone'];

        $payload = (new PayloadGenerator(
            $merchantId,
            $this->getWebhookUrl(),
            $this->getSuccessUrl(),
            $this->getCancelUrl()
        ))
            ->setUpCustomer(
                $fields['firstName'] ?? $phone,
                $fields['lastName'] ?? $phone,
                $phone,
                $fields['country'] ?? 'ZW',
                $fields['email'] ?? ''
            )
            ->setUpTransaction(
                (float) $fields['amount'],
                $fields['currency'],
                $fields['reference'] ?? '',
                $fields['description'] ?? ''
            )
            ->redirectPayload(
                (bool) ($fields['coc'] ?? false),
                (bool) ($fields['cod'] ?? false)
            );

        try {
            $response = $this->contipay
                ->setAppMode($this->mode)
                ->setPaymentMethod('redirect')
                ->process($payload);
        } catch (\Throwable $th) {
            $response = json_encode([
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }

        return new CheckoutSession($payload['reference'], $response);
    }
}
