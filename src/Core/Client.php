<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Core;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use RuntimeException;
use SanixAI\Utils\Core\Checksums\ContipayChecksum;

/**
 * Low-level HTTP client for the ContiPay API.
 *
 * Every request method returns the API's JSON response body. Transport and HTTP
 * errors are returned as JSON too, so callers always get a decodable string:
 * {"status": "error", "message": "...", "outcome": "failed"|"unknown"}.
 *
 * "failed" means ContiPay did not act on the request, so it is safe to retry.
 * "unknown" means it may have been processed: look up the status by reference
 * before retrying, or a retry can charge or pay out twice.
 */
class Client
{
    public const MODE_DEV = 'DEV';
    public const MODE_LIVE = 'LIVE';

    public const ACQUIRE_ENDPOINT = 'acquire/payment';
    public const DISBURSE_ENDPOINT = 'disburse/payment';

    public const DEFAULT_UAT_URL = 'https://api-uat.contipay.net';
    public const DEFAULT_LIVE_URL = 'https://api.contipay.net';

    /**
     * Seconds to wait for a connection to ContiPay before giving up.
     * Nothing has been sent when this fires, so it is always safe to retry.
     */
    protected const CONNECT_TIMEOUT = 10;

    /**
     * Seconds to wait for ContiPay to respond before giving up.
     * The request may already have been processed when this fires.
     */
    protected const TIMEOUT = 60;

    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_UNKNOWN = 'unknown';

    /**
     * cURL errors raised before the request is sent: couldn't resolve proxy,
     * couldn't resolve host, couldn't connect, TLS handshake failed.
     */
    protected const NOT_SENT_CURL_ERRORS = [5, 6, 7, 35];

    /**
     * @var string API token for authentication
     */
    protected string $token;
    /**
     * @var string API secret for authentication
     */
    protected string $secret;
    /**
     * @var string Environment mode, one of the MODE_* constants
     */
    protected string $mode = self::MODE_DEV;
    /**
     * @var string HTTP method for payment requests (POST for direct, PUT for redirect)
     */
    protected string $paymentMethod = 'POST';
    /**
     * @var string UAT (test) API URL
     */
    protected string $uatURL = self::DEFAULT_UAT_URL;
    /**
     * @var string Live API URL
     */
    protected string $liveURL = self::DEFAULT_LIVE_URL;
    /**
     * @var HttpClient|null Guzzle client for the current base URL, created on first use
     */
    protected ?HttpClient $client = null;

    /**
     * @param string $token  API token
     * @param string $secret API secret
     */
    public function __construct(string $token, string $secret)
    {
        $this->token = $token;
        $this->secret = $secret;
    }

    /**
     * Set the environment mode.
     *
     * @param string $mode 'DEV' for UAT, 'LIVE' for production (case-insensitive)
     * @throws InvalidArgumentException If the mode is not recognised
     * @return self
     */
    public function setAppMode(string $mode = self::MODE_DEV): self
    {
        $mode = strtoupper($mode);
        if ($mode !== self::MODE_DEV && $mode !== self::MODE_LIVE) {
            throw new InvalidArgumentException("Invalid mode '{$mode}': expected 'dev' or 'live'.");
        }

        if ($mode !== $this->mode) {
            $this->mode = $mode;
            $this->client = null;
        }

        return $this;
    }

    /**
     * Get the current environment mode.
     *
     * @return string One of the MODE_* constants
     */
    public function getAppMode(): string
    {
        return $this->mode;
    }

    /**
     * Override the UAT and Live API URLs.
     *
     * @param string $devURL  UAT URL
     * @param string $liveURL Live URL
     * @return self
     */
    public function updateURL(string $devURL, string $liveURL): self
    {
        $this->uatURL = $devURL;
        $this->liveURL = $liveURL;
        $this->client = null;
        return $this;
    }

    /**
     * Get the base URL for the current mode.
     *
     * @return string
     */
    public function getBaseURL(): string
    {
        return $this->mode === self::MODE_LIVE ? $this->liveURL : $this->uatURL;
    }

    /**
     * Set the payment flow.
     *
     * @param string $method 'direct' sends a POST; anything else is a redirect and sends a PUT
     * @return self
     */
    public function setPaymentMethod(string $method = 'direct'): self
    {
        $this->paymentMethod = strtolower($method) === 'direct' ? 'POST' : 'PUT';
        return $this;
    }

    /**
     * Send a payment request.
     *
     * @param array $payload Payment payload, e.g. from PayloadGenerator
     * @return string JSON response from API
     */
    public function process(array $payload): string
    {
        return $this->send($this->paymentMethod, self::ACQUIRE_ENDPOINT, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload,
        ]);
    }

    /**
     * Send a disbursement, signed with the merchant's private key.
     *
     * @param array  $payload    Disbursement payload, e.g. from PayloadGenerator::directPayload()
     * @param string $privateKey PEM-encoded private key
     * @throws RuntimeException If the payload cannot be signed
     * @return string JSON response from API
     */
    public function disburse(array $payload, string $privateKey): string
    {
        $checksum = $this->generateChecksum($payload, $privateKey);

        return $this->send($this->paymentMethod, self::DISBURSE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
                'checksum' => $checksum,
            ],
            'json' => $payload,
        ]);
    }

    /**
     * Look up the status of a payment or disbursement by its reference.
     *
     * @param string   $endpoint   API endpoint, ACQUIRE_ENDPOINT or DISBURSE_ENDPOINT
     * @param string   $reference  Transaction reference
     * @param int|null $merchantId Merchant ID the transaction belongs to
     * @param array    $extra      Additional query parameters, e.g. ['providerCode' => 'EC']
     * @return string JSON response from API
     */
    public function queryStatus(string $endpoint, string $reference, ?int $merchantId = null, array $extra = []): string
    {
        $query = ['merchantRef' => $reference];
        if ($merchantId !== null) {
            $query['merchantId'] = $merchantId;
        }
        $query += array_filter($extra, fn($value) => $value !== null && $value !== '');

        return $this->send('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Sign a disbursement payload.
     *
     * ContiPay verifies an RSA-SHA256 signature over the API token, reference,
     * merchant ID, account number and amount, concatenated in that order.
     *
     * @param array  $payload    Disbursement payload
     * @param string $privateKey PEM-encoded private key
     * @throws RuntimeException If the key cannot be read or signing fails
     * @return string Base64-encoded signature
     */
    public function generateChecksum(array $payload, string $privateKey): string
    {
        $data = $this->token
            . ($payload['transaction']['reference'] ?? '')
            . ($payload['transaction']['merchantId'] ?? '')
            . ($payload['accountDetails']['accountNumber'] ?? '')
            . ($payload['transaction']['amount'] ?? '');

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('Failed to read the private key used to sign the disbursement.');
        }

        try {
            return (new ContipayChecksum())->generateChecksum($data, true, $key);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to sign the disbursement: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send an authenticated request and return the response body.
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint relative to the base URL
     * @param array  $options  Guzzle request options
     * @return string JSON response from API, or a JSON error
     */
    protected function send(string $method, string $endpoint, array $options): string
    {
        $options['auth'] = [$this->token, $this->secret];
        $options['headers'] = ['Accept' => 'application/json'] + ($options['headers'] ?? []);

        try {
            return (string) $this->http()->request($method, '/' . ltrim($endpoint, '/'), $options)->getBody();
        } catch (ConnectException $e) {
            // Guzzle also raises this for timeouts after the request was sent, so only
            // errors that happen before sending count as a definite failure.
            $errno = $e->getHandlerContext()['errno'] ?? null;
            $notSent = in_array($errno, static::NOT_SENT_CURL_ERRORS, true);

            return $this->errorResponse($e->getMessage(), $notSent ? self::OUTCOME_FAILED : self::OUTCOME_UNKNOWN);
        } catch (RequestException $e) {
            // ContiPay explains rejected requests in a JSON body; pass it through when present.
            $response = $e->getResponse();
            $body = $response !== null ? (string) $response->getBody() : '';
            if ($body !== '' && json_decode($body) !== null) {
                return $body;
            }

            // A 4xx was rejected; a 5xx (e.g. a gateway timeout) or no response may have been processed.
            $rejected = $response !== null && $response->getStatusCode() < 500;

            return $this->errorResponse($e->getMessage(), $rejected ? self::OUTCOME_FAILED : self::OUTCOME_UNKNOWN);
        } catch (GuzzleException $e) {
            return $this->errorResponse($e->getMessage(), self::OUTCOME_UNKNOWN);
        }
    }

    /**
     * Get the Guzzle client for the current base URL, creating it if needed.
     *
     * @return HttpClient
     */
    protected function http(): HttpClient
    {
        if ($this->client === null) {
            $this->client = new HttpClient([
                'base_uri' => $this->getBaseURL(),
                'connect_timeout' => static::CONNECT_TIMEOUT,
                'timeout' => static::TIMEOUT,
            ]);
        }

        return $this->client;
    }

    /**
     * Build a JSON error response.
     *
     * @param string $message Error message
     * @param string $outcome One of the OUTCOME_* constants
     * @return string
     */
    protected function errorResponse(string $message, string $outcome): string
    {
        return json_encode([
            'status' => 'error',
            'message' => $message,
            'outcome' => $outcome,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
