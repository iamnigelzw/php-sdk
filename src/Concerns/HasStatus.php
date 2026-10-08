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
        return $this->checkStatus('transactionStatus', $reference);
    }

    /**
     * Get the status of a disbursement by its reference.
     *
     * @param string $reference Disbursement reference
     * @throws \InvalidArgumentException
     * @return string JSON-encoded status response or error
     */
    public function getDisbursementStatus(string $reference): string
    {
        return $this->checkStatus('disbursementStatus', $reference);
    }

    /**
     * Query the Contipay status endpoint for the given reference.
     *
     * @param string $method    Core status method to call
     * @param string $reference Transaction reference
     * @throws \InvalidArgumentException
     * @return string JSON-encoded status response or error
     */
    protected function checkStatus(string $method, string $reference): string
    {
        if (empty($reference)) {
            throw new \InvalidArgumentException("Missing required field: reference");
        }

        try {
            return $this->contipay
                ->setAppMode($this->mode)
                ->{$method}($reference);
        } catch (\Throwable $th) {
            return json_encode([
                'status' => 'error',
                'message' => $th->getMessage()
            ]);
        }
    }
}
