<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk;

use InvalidArgumentException;

/**
 * A ContiPay transaction notification: the body ContiPay POSTs to your webhook URL.
 *
 * Also accepts the response from getTransactionStatus() / getDisbursementStatus(),
 * where the same fields sit under a "transaction" key, so a webhook can be
 * confirmed against the API and read the same way.
 */
class Webhook
{
    /** Awaiting confirmation or further processing */
    public const STATUS_PENDING = 0;
    /** Successfully paid */
    public const STATUS_PAID = 1;
    /** Refunded back to the client from the merchant */
    public const STATUS_REFUNDED = 2;
    /** Failed to process due to an error */
    public const STATUS_ERROR = 3;
    /** Declined */
    public const STATUS_DECLINED = 4;
    /** Confirmed */
    public const STATUS_CONFIRMED = 5;
    /** Queued */
    public const STATUS_QUEUED = 6;
    /** Approved */
    public const STATUS_APPROVED = 7;
    /** Posted to an external party for further processing */
    public const STATUS_SUBMITTED = 8;

    protected const STATUS_NAMES = [
        'PENDING' => self::STATUS_PENDING,
        'PAID' => self::STATUS_PAID,
        'REFUNDED' => self::STATUS_REFUNDED,
        'ERROR' => self::STATUS_ERROR,
        'DECLINED' => self::STATUS_DECLINED,
        'CONFIRMED' => self::STATUS_CONFIRMED,
        'QUEUED' => self::STATUS_QUEUED,
        'APPROVED' => self::STATUS_APPROVED,
        'SUBMITTED' => self::STATUS_SUBMITTED,
    ];

    /**
     * @var array<string, mixed> Transaction fields
     */
    protected array $data;

    /**
     * @param array<string, mixed> $payload Decoded webhook body or status response
     */
    public function __construct(array $payload)
    {
        $this->data = isset($payload['transaction']) && is_array($payload['transaction'])
            ? $payload['transaction'] + $payload
            : $payload;
    }

    /**
     * Read the notification from the current HTTP request body.
     *
     * @throws InvalidArgumentException If the body is not a JSON object
     * @return self
     */
    public static function fromRequest(): self
    {
        return static::fromJson((string) file_get_contents('php://input'));
    }

    /**
     * Read the notification from a raw JSON body.
     *
     * @param string $json
     * @throws InvalidArgumentException If the body is not a JSON object
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid ContiPay webhook: body is not a JSON object.');
        }

        return new static($payload);
    }

    /**
     * The numeric status, one of the STATUS_* constants.
     *
     * Uses statusCode when present, otherwise maps the textual status.
     * Anything unrecognised is treated as STATUS_ERROR so it is never mistaken for a payment.
     *
     * @return int
     */
    public function statusCode(): int
    {
        foreach (['statusCode', 'status'] as $key) {
            $value = $this->data[$key] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return self::STATUS_NAMES[strtoupper((string) ($this->data['status'] ?? ''))] ?? self::STATUS_ERROR;
    }

    /**
     * Whether the money has been received (paid or confirmed).
     */
    public function isPaid(): bool
    {
        return in_array($this->statusCode(), [self::STATUS_PAID, self::STATUS_CONFIRMED], true);
    }

    /**
     * Whether the transaction is still in progress and a later notification will follow.
     */
    public function isPending(): bool
    {
        return in_array(
            $this->statusCode(),
            [self::STATUS_PENDING, self::STATUS_QUEUED, self::STATUS_APPROVED, self::STATUS_SUBMITTED],
            true
        );
    }

    /**
     * Whether the transaction was refunded.
     */
    public function isRefunded(): bool
    {
        return $this->statusCode() === self::STATUS_REFUNDED;
    }

    /**
     * Whether the transaction failed: errored, declined, or an unrecognised status.
     */
    public function isFailed(): bool
    {
        return !$this->isPaid() && !$this->isPending() && !$this->isRefunded();
    }

    /**
     * Your reference for the transaction, as sent with the payment.
     */
    public function reference(): ?string
    {
        return isset($this->data['merchantRef']) ? (string) $this->data['merchantRef'] : null;
    }

    /**
     * ContiPay's own transaction reference.
     */
    public function contiPayReference(): ?string
    {
        return isset($this->data['contiPayRef']) ? (string) $this->data['contiPayRef'] : null;
    }

    /**
     * The provider's reference, e.g. the EcoCash transaction ID.
     */
    public function providerReference(): ?string
    {
        return isset($this->data['correlator']) ? (string) $this->data['correlator'] : null;
    }

    public function merchantId(): ?int
    {
        return isset($this->data['merchantId']) ? (int) $this->data['merchantId'] : null;
    }

    public function amount(): ?float
    {
        return isset($this->data['amount']) ? (float) $this->data['amount'] : null;
    }

    public function currency(): ?string
    {
        return $this->data['currencyCode'] ?? null;
    }

    public function providerCode(): ?string
    {
        return $this->data['providerCode'] ?? null;
    }

    public function providerName(): ?string
    {
        return $this->data['providerName'] ?? null;
    }

    /**
     * The transaction type, e.g. 'acquire' for a payment.
     */
    public function methodCode(): ?string
    {
        return $this->data['methodCode'] ?? null;
    }

    public function message(): ?string
    {
        return $this->data['message'] ?? null;
    }

    /**
     * Read any field from the notification.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
