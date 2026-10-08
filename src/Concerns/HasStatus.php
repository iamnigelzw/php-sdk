<?php

namespace ContiPay\PhpSdk\Concerns;

trait HasStatus
{
    /**
     * Get the status of a payment by its reference.
     *
     * @param string $reference Payment reference
     * @throws \InvalidArgumentException
     * @return string JSON-encoded status response or error
     */
    public function getTransactionStatus(string $reference): string
    {
        return $this->checkStatus('acquire/payment', $reference);
    }

    /**
     * Get the status of a disbursement by its reference.
     *
     * @param string $reference Disbursement reference
     * @param string|null $providerCode Optional provider code to narrow the lookup, e.g. 'EC'
     * @throws \InvalidArgumentException
     * @return string JSON-encoded status response or error
     */
    public function getDisbursementStatus(string $reference, ?string $providerCode = null): string
    {
        return $this->checkStatus('disburse/payment', $reference, ['providerCode' => $providerCode]);
    }

    /**
     * Query the Contipay status endpoint for the given reference.
     *
     * @param string $endpoint  Status endpoint to query
     * @param string $reference Transaction reference
     * @param array  $extra     Additional query parameters
     * @throws \InvalidArgumentException
     * @return string JSON-encoded status response or error
     */
    protected function checkStatus(string $endpoint, string $reference, array $extra = []): string
    {
        if (empty($reference)) {
            throw new \InvalidArgumentException("Missing required field: reference");
        }

        try {
            return $this->contipay
                ->setAppMode($this->mode)
                ->queryStatus($endpoint, $reference, $this->merchantId ?? null, $extra);
        } catch (\Throwable $th) {
            return json_encode([
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }
    }
}
