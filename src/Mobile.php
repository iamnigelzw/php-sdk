<?php

namespace ContiPay\PhpSdk;

use ContiPay\PhpSdk\Payment\WalletPayment;
use ContiPay\PhpSdk\Support\ProviderRegistry;

/**
 * Collect payments from mobile money wallets.
 *
 * @method string ecocash(array $fields)
 * @method string onemoney(array $fields)
 * @method string omari(array $fields)
 * @method string innbucks(array $fields)
 * @method string telecash(array $fields)
 * @method string mtn(array $fields)
 * @method string airtel(array $fields)
 * @method string zamtel(array $fields)
 * @method string equitel(array $fields)
 * @method string mpesa(array $fields)
 * @method string orange(array $fields)
 * @method string tigo(array $fields)
 * @method string mobile(array $fields)
 */
class Mobile extends WalletPayment
{
    protected function providerType(): string
    {
        return ProviderRegistry::TYPE_MOBILE;
    }
}
