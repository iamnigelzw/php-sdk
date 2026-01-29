# ContiPay SDK

A simple and secure PHP SDK for integrating payments with the ContiPay platform. Process mobile money and card payments with ease.

[![Latest Version](https://img.shields.io/packagist/v/contipay/php-sdk.svg)](https://packagist.org/packages/contipay/php-sdk)
[![License](https://img.shields.io/packagist/l/contipay/php-sdk.svg)](LICENSE.md)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat&labelColor=18181b)](https://whyphp.dev)


##  Installation

Install the package via Composer:

```bash
composer require contipay/php-sdk
```

##  Quick Start

```php
<?php

use ContiPay\PhpSdk\Card;
use ContiPay\PhpSdk\Mobile;

require_once __DIR__ . '/vendor/autoload.php';

// Initialize the SDK
$contipay = new Mobile('your_api_key', 'your_api_secret');

// Process a payment
$response = $contipay->mobile([
    'amount' => '50.00',
    'currency' => 'ZWG',
    'phone' => '0782000340',
    'reference' => uniqid('PAY-'),
    'provider' => 'EcoCash',
    'code' => 'EC'
]);

OR

// Initialize the SDK
$contipay = new Card('your_api_key', 'your_api_secret');

// Process a payment
$response = $contipay->card([
    'accountNumber' => '4111111111111111',
    'accountExpiry' => '12/26',
    'cvv' => '123',
    'phone' => '0782000340',
    'amount' => '50.00',
    'currency' => 'USD',
    'reference' => uniqid('CARD-'),
    'provider' => 'Visa',
    'code' => 'VA'
]);
```

## Configuration

### Basic Setup

The SDK provides two main classes:
- `Mobile` - For mobile money payments
- `Card` - For card payments

```php
<?php

use ContiPay\PhpSdk\Mobile;
use ContiPay\PhpSdk\Card;

require_once __DIR__ . '/vendor/autoload.php';

// Mobile payments
$mobile = new Mobile($apiKey, $apiSecret, $mode, $method);

// Card payments
$card = new Card($apiKey, $apiSecret, $mode, $method);
```

### Configuration Parameters

| Parameter | Description | Options | Default |
|-----------|-------------|----------|---------|
| `$apiKey` | Your ContiPay API key | - | Required |
| `$apiSecret` | Your ContiPay API secret | - | Required |
| `$mode` | Environment mode | `dev`, `live` | `dev` |
| `$method` | Payment flow | `direct`, `redirect` | `direct` |

## Mobile Payments

### Supported Providers

- ✅ **EcoCash**
- ✅ **OneMoney**
- ✅ **Omari**
- ✅ **InnBucks**

---

* **EcoCash** (`$contipay->ecocash([...])`)
* **OneMoney** (`$contipay->onemoney([...])`)
* **Omari** (`$contipay->omari([...])`)
* **InnBucks** (`$contipay->innbucks([...])`)
* **All In One** (`$contipay->mobile([...])`)

---

### Example: Process Mobile Payment

```php
<?php

use ContiPay\PhpSdk\Mobile;

require_once __DIR__ . '/vendor/autoload.php';

$contipay = new Mobile('your_api_key', 'your_api_secret');

// Configure merchant details
$contipay->setMerchantId(12345);
$contipay->setWebhookUrl('https://your-domain.com/webhook');

try {
    $response = $contipay->ecocash([
        'amount' => '50.00',
        'currency' => 'USD',
        'phone' => '0782000340',
        'reference' => uniqid('ECO-'),
    ]);
    
    $result = json_decode($response, true);
    // Handle success
} catch (\Throwable $e) {
    // Handle error
}
```

OR

#### Provider Code & Name Options

| code | provider | 
|-----------|-------------|
| `EC` | `EcoCash` |
| `OM` | `OneMoney` |
| `OC` | `Omari` | 
| `IB` | `InnBucks` | 

```php
<?php

use ContiPay\PhpSdk\Mobile;

require_once __DIR__ . '/vendor/autoload.php';

$contipay = new Mobile('your_api_key', 'your_api_secret');

// Configure merchant details
$contipay->setMerchantId(12345);
$contipay->setWebhookUrl('https://your-domain.com/webhook');

// Required for redirect flow
$contipay->setSuccessUrl('https://your-domain.com/success');
$contipay->setErrorUrl('https://your-domain.com/error');

try {
  $response = $contipay->mobile([
        'amount' => '50.00',
        'currency' => 'USD',
        'phone' => '0782000340',
        'reference' => uniqid('PAY-'),
        'provider' => 'EcoCash',
        'code' => 'EC'
    ]);

    $result = json_decode($response, true);
    // Handle success
} catch (\Throwable $e) {
    // Handle error
}
```

## Card Payments

### Supported Cards

- ✅ **Visa**
- ✅ **MasterCard**
- ✅ **ZimSwitch**

---

* **Visa** (`$contipay->visa([...])`)
* **MasterCard** (`$contipay->mastercard([...])`)
* **ZimSwitch** (`$contipay->zimswitch([...])`)
* **All In One** (`$contipay->card([...])`)

---

### Example: Process Card Payment

```php
<?php

use ContiPay\PhpSdk\Card;

require_once __DIR__ . '/vendor/autoload.php';

$contipay = new Card('your_api_key', 'your_api_secret', 'live', 'redirect');

// Configure merchant details
$contipay->setMerchantId(12345);
$contipay->setWebhookUrl('https://your-domain.com/webhook');

// Required for redirect flow
$contipay->setSuccessUrl('https://your-domain.com/success');
$contipay->setErrorUrl('https://your-domain.com/error');

try {
    $response = $contipay->visa([
        'firstName' => 'John',
        'lastName' => 'Doe',
        'accountNumber' => '4111111111111111',
        'accountExpiry' => '12/26',
        'cvv' => '123',
        'phone' => '0782000340',
        'email' => 'john@example.com',
        'amount' => '50.00',
        'currency' => 'USD',
        'country' => 'ZW',
        'reference' => uniqid('CARD-'),
        'description' => 'Premium Subscription'
    ]);
    
    $result = json_decode($response, true);
    // Handle success
} catch (\Throwable $e) {
    // Handle error
}
```

OR

#### Provider Code & Name Options

| code | provider | 
|-----------|-------------|
| `ZS` | `ZimSwitch` |
| `VA` | `Visa` |
| `MA` | `MasterCard` | 


```php
<?php

use ContiPay\PhpSdk\Card;

require_once __DIR__ . '/vendor/autoload.php';

$contipay = new Card('your_api_key', 'your_api_secret', 'dev', 'redirect');

// Configure merchant details
$contipay->setMerchantId(12345);
$contipay->setWebhookUrl('https://your-domain.com/webhook');

// Required for redirect flow
$contipay->setSuccessUrl('https://your-domain.com/success');
$contipay->setErrorUrl('https://your-domain.com/error');

try {
  $response = $contipay->card([
        'accountNumber' => '4111111111111111',
        'accountExpiry' => '12/26',
        'cvv' => '123',
        'phone' => '0782000340',
        'amount' => '50.00',
        'currency' => 'USD',
        'reference' => uniqid('CARD-'),
        'provider' => 'Visa',
        'code' => 'VA'
    ]);

    $result = json_decode($response, true);
    // Handle success
} catch (\Throwable $e) {
    // Handle error
}
```

## Security

- All API requests are encrypted using TLS
- Sensitive data is never logged
- API credentials are required for all requests

## Additional Resources

- [Support Portal](https://contipay.co.zw)

## 📄 License

This SDK is open-sourced software licensed under the [MIT license](LICENSE.md).