<?php

namespace ContiPay\PhpSdk\Core;

use Contipay\Core\Contipay;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class Client extends Contipay
{
    /**
     * Look up the status of a payment or disbursement by its reference.
     *
     * @param string $endpoint   API endpoint, e.g. 'acquire/payment' or 'disburse/payment'
     * @param string $reference  Transaction reference
     * @param int|null $merchantId Merchant ID the transaction belongs to
     * @param array $extra       Additional query parameters, e.g. ['providerCode' => 'EC']
     * @return string JSON response from API
     */
    public function queryStatus(string $endpoint, string $reference, ?int $merchantId = null, array $extra = []): string
    {
        $query = ['merchantRef' => $reference];
        if ($merchantId !== null) {
            $query['merchantId'] = $merchantId;
        }
        $query += array_filter($extra, fn($value) => $value !== null && $value !== '');

        try {
            $response = $this->client->request('GET', "/{$endpoint}", [
                'auth' => [$this->token, $this->secret],
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'query' => $query,
            ]);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            // ContiPay explains rejected lookups in a JSON body; pass it through when present.
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            if ($body !== '' && json_decode($body) !== null) {
                return $body;
            }
            return $this->errorResponse($e->getMessage());
        } catch (GuzzleException $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Build a JSON error response.
     *
     * @param string $message Error message
     * @return string
     */
    protected function errorResponse(string $message): string
    {
        return json_encode([
            'status' => 'Error',
            'message' => $message,
        ]);
    }
}
