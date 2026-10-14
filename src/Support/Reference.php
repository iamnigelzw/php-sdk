<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Support;

class Reference
{
    /**
     * Generate a numeric reference string of specified length.
     *
     * @param int      $len    Length of the reference (default: 6)
     * @param int|null $number Optional number to use as reference. If null, a random number is generated.
     * @return string Zero-padded reference string
     */
    public function generate(int $len = 6, ?int $number = null): string
    {
        $input = $number ?? random_int(0, (10 ** $len) - 1);
        return str_pad((string) $input, $len, '0', STR_PAD_LEFT);
    }
}
