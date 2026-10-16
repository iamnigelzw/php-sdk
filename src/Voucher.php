<?php

namespace ContiPay\PhpSdk;

use ContiPay\PhpSdk\Payment\WalletPayment;
use ContiPay\PhpSdk\Support\ProviderRegistry;

/**
 * Collect payments made with ContiPay vouchers.
 *
 * Behaves exactly like {@see Mobile}, but routes to the voucher provider and
 * only accepts the currencies vouchers are issued in.
 *
 * @method string voucher(array $fields)
 */
class Voucher extends WalletPayment
{
    protected function providerType(): string
    {
        return ProviderRegistry::TYPE_VOUCHER;
    }
}
