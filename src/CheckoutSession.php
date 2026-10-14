<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk;

/**
 * The result of Checkout::create(): where to send the customer, and what ContiPay said.
 */
class CheckoutSession
{
    protected string $reference;
    protected string $json;
    protected array $data;

    /**
     * @param string $reference Reference the checkout was created with
     * @param string $json      Raw JSON response from ContiPay
     */
    public function __construct(string $reference, string $json)
    {
        $this->reference = $reference;
        $this->json = $json;

        $data = json_decode($json, true);
        $this->data = is_array($data) ? $data : [];
    }

    /**
     * URL of ContiPay's hosted payment page, or null if the checkout was not created.
     *
     * @return string|null
     */
    public function url(): ?string
    {
        $url = $this->data['redirectUrl'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Whether ContiPay returned a payment page to send the customer to.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->url() !== null;
    }

    /**
     * Reference to use for status lookups and to match webhooks.
     *
     * @return string
     */
    public function reference(): string
    {
        return $this->reference;
    }

    /**
     * Message from ContiPay, or from the SDK when the request failed.
     *
     * @return string|null
     */
    public function message(): ?string
    {
        $message = $this->data['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    /**
     * The decoded response.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * The raw JSON response.
     *
     * @return string
     */
    public function toJson(): string
    {
        return $this->json;
    }
}
