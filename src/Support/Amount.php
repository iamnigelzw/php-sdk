<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Support;

use InvalidArgumentException;

class Amount
{
    /**
     * Validate a payment amount: a positive number with at most 2 decimal places.
     *
     * Rejects negatives, zero, exponents ('1e3'), padding (' 5') and sub-cent values.
     *
     * @param mixed $amount Amount as given by the caller, e.g. '10.50', 10.5 or 10
     * @throws InvalidArgumentException If the amount is not valid
     * @return float
     */
    public static function parse($amount): float
    {
        $value = is_string($amount) || is_int($amount) || is_float($amount) ? (string) $amount : '';

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value) || (float) $value <= 0) {
            $shown = is_scalar($amount) ? (string) $amount : get_debug_type($amount);
            throw new InvalidArgumentException(
                "Invalid amount: '{$shown}' must be a positive number with at most 2 decimal places"
            );
        }

        return (float) $value;
    }
}
