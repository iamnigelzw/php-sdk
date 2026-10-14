# ContiPay SDK

A simple and secure PHP SDK for integrating payments with the ContiPay platform. Process mobile money and card payments with ease.

[![Latest Version](https://img.shields.io/packagist/v/contipay/php-sdk.svg)](https://packagist.org/packages/contipay/php-sdk)
[![License](https://img.shields.io/packagist/l/contipay/php-sdk.svg)](LICENSE.md)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat&labelColor=18181b)](https://whyphp.dev)


## Requirements

- PHP 8.1 or newer, with the `json` and `openssl` extensions
- A ContiPay merchant account with an API key and secret

##  Installation

Install the package via Composer:

```bash
composer require contipay/php-sdk
```

If you are on PHP 7.4 or 8.0, stay on 1.x: `composer require contipay/php-sdk:^1.2`.

##  Quick Start

```php
<?php

use ContiPay\PhpSdk\Card;
use ContiPay\PhpSdk\Mobile;

require_once __DIR__ . '/vendor/autoload.php';

// Initialize the SDK
$contipay = new Mobile('your_api_key', 'your_api_secret');
$contipay->setMerchantId(12345);

 // update Contipay URL (optional)
$contipay->updateContipayURL('new-dev-url', 'new-live-url');

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
$contipay->setMerchantId(12345);

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

The SDK provides these main classes:
- `Mobile` - For mobile money payments
- `Card` - For card payments
- `Checkout` - For sending customers to ContiPay's hosted payment page (see [Hosted Checkout](#hosted-checkout))
- `Disbursement` - For sending money to mobile wallets (see [Disbursements](#disbursements))
- `Webhook` - For reading payment notifications (see [Webhooks](#webhooks))

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

### Error Handling

Mistakes in your code throw exceptions before anything is sent to ContiPay:

- `LogicException` if you make a payment or disbursement without calling `setMerchantId()` first
- `InvalidArgumentException` if a required field is missing or `amount` is not a positive number with at most 2 decimal places (`"10"`, `"10.5"` and `"10.50"` are fine; `"-5"`, `"1e3"` and `"1.005"` are not)
- `BadMethodCallException` if you call a provider the class does not support

Everything else, including API rejections and network failures, comes back as JSON. When the SDK builds the error itself, it has a `status` of `"error"`, a `message`, and an `outcome`:

- `"failed"`: ContiPay did not act on the request (for example, the connection was refused). It is safe to retry.
- `"unknown"`: the request may have been processed (for example, it timed out after sending, or a gateway returned a 5xx). Look up the status by reference before you retry, or you can charge the customer or pay out twice.

```php
$result = json_decode($response, true);

if (($result['outcome'] ?? null) === 'unknown') {
    $status = $contipay->getTransactionStatus($reference); // or getDisbursementStatus()
}
```

The SDK waits up to 10 seconds to connect and 60 seconds for a response.

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

## Hosted Checkout

Use `Checkout` to send the customer to ContiPay's payment page, where they choose how to pay. You never handle card or wallet details yourself.

```php
<?php

use ContiPay\PhpSdk\Checkout;

require_once __DIR__ . '/vendor/autoload.php';

$checkout = new Checkout('your_api_key', 'your_api_secret', 'live');

$checkout->setMerchantId(12345)
    ->setWebhookUrl('https://your-domain.com/webhook')
    ->setSuccessUrl('https://your-domain.com/success')
    ->setCancelUrl('https://your-domain.com/cancel');

$session = $checkout->create([
    'amount' => '50.00',
    'currency' => 'USD',
    'phone' => '0782000340',
    'reference' => 'ORDER-1001',
    'description' => 'Order #1001',
]);

if ($session->successful()) {
    header('Location: ' . $session->url());
    exit;
}

echo $session->message();
```

| Field | Description | Required |
|-------|-------------|----------|
| `amount` | Amount to charge | Yes |
| `currency` | Currency code, e.g. `USD`, `ZWG` | Yes |
| `phone` | Customer phone number | Yes |
| `reference` | Your order reference. Generated when left out | No |
| `description` | Shown on the payment page | No |
| `firstName`, `lastName` | Customer name. Defaults to the phone number | No |
| `email` | Customer email | No |
| `country` | Customer country code | No (`ZW`) |
| `cod`, `coc` | Cash on delivery / cash on collection | No (`false`) |

`create()` returns a `CheckoutSession`:

- `url()` - the payment page URL, or `null` if ContiPay did not create the checkout
- `successful()` - whether there is a URL to send the customer to
- `reference()` - the reference to store against the order, for status lookups and matching webhooks
- `message()` - the message from ContiPay, or the error when the request failed
- `toArray()` / `toJson()` - the full response

The customer pays on ContiPay's page, so confirm payment from your webhook (see [Webhooks](#webhooks)) or with `$checkout->getTransactionStatus($session->reference())`. Do not treat a visit to your success URL as proof of payment. Straight after `create()`, a status lookup can come back as "not found" because the customer has not paid yet.

To show a pay button, create the session on your server and link to its URL. The button cannot call ContiPay directly from the browser, because that would expose your API secret.

## Disbursements

Use `Disbursement` to send money from your merchant account to a mobile wallet. Every disbursement is signed with your merchant private key, so pass it when you create the instance. You can pass the PEM string itself or a path to a `.pem` file. An invalid key throws an `InvalidArgumentException`.

```php
new Disbursement($apiKey, $apiSecret, $privateKey, $mode);
```

| Parameter | Description | Options | Default |
|-----------|-------------|----------|---------|
| `$apiKey` | Your ContiPay API key | - | Required |
| `$apiSecret` | Your ContiPay API secret | - | Required |
| `$privateKey` | PEM private key or path to a `.pem` file | - | Required |
| `$mode` | Environment mode | `dev`, `live` | `dev` |

### Supported Providers

* **EcoCash** (`$disbursement->ecocash([...])`)
* **OneMoney** (`$disbursement->onemoney([...])`)
* **Omari** (`$disbursement->omari([...])`)
* **InnBucks** (`$disbursement->innbucks([...])`)
* **All In One** (`$disbursement->mobile([...])`, which needs `provider` and `code` from the [provider table](#provider-code--name-options))

### Example: Send a Disbursement

```php
<?php

use ContiPay\PhpSdk\Disbursement;

require_once __DIR__ . '/vendor/autoload.php';

$disbursement = new Disbursement(
    'your_api_key',
    'your_api_secret',
    __DIR__ . '/keys/contipay-private.pem', // or the PEM string
    'dev'
);

$disbursement->setMerchantId(12345)
    ->setWebhookUrl('https://your-domain.com/webhook');

$reference = uniqid('DISB-');

$response = $disbursement->ecocash([
    'amount' => '20.00',
    'currency' => 'USD',
    'phone' => '0782000340',
    'reference' => $reference,
    // optional
    'accountName' => 'Nigel Jaure',
    'firstName' => 'Nigel',
    'lastName' => 'Jaure',
    'email' => 'nigel@example.com',
    'description' => 'Salary payout',
]);

$result = json_decode($response, true);

// Check the disbursement later
$status = $disbursement->getDisbursementStatus($reference);
```

`amount`, `currency`, `phone` and `reference` are required. A missing field throws an `InvalidArgumentException`. API errors are returned as JSON with a `status` and `message`. Never retry a disbursement whose `outcome` is `"unknown"` without checking `getDisbursementStatus()` first (see [Error Handling](#error-handling)).

## Transaction Status

`Mobile`, `Card` and `Disbursement` can look up the status of a payment or disbursement by its reference. The lookup uses the instance's mode (`dev`/`live`) and any URLs set with `updateContipayURL()`.

```php
use ContiPay\PhpSdk\Mobile;

$contipay = new Mobile('your-api-key', 'your-api-secret', 'dev');
$contipay->setMerchantId(123); // required by ContiPay for status lookups

// Status of a payment
$response = $contipay->getTransactionStatus('TXN-12345');

// Status of a disbursement
$response = $contipay->getDisbursementStatus('DISB-12345');

// Optionally narrow a disbursement lookup to a provider
$response = $contipay->getDisbursementStatus('DISB-12345', 'EC');

$result = json_decode($response, true);
```

An empty reference throws an `InvalidArgumentException`. API errors are returned as JSON with a `status` and `message`.

Payment status calls `GET /acquire/payment?merchantRef=...&merchantId=...` and disbursement status calls `GET /disburse/payment?merchantRef=...&merchantId=...`, adding `&providerCode=...` when you pass one. Both use the same API key and secret as payments, and send the merchant ID set with `setMerchantId()`.

## Webhooks

ContiPay POSTs a JSON notification to the URL you set with `setWebhookUrl()` when a transaction changes status. `Webhook` reads it:

```php
use ContiPay\PhpSdk\Webhook;

$webhook = Webhook::fromRequest(); // or Webhook::fromJson($rawBody) / new Webhook($array)

$order = findOrder($webhook->reference()); // your merchantRef

if ($webhook->isPaid()) {
    // paid or confirmed: fulfil the order
} elseif ($webhook->isPending()) {
    // pending, queued, approved or submitted: wait for the next notification
} elseif ($webhook->isRefunded()) {
    // refunded
} else {
    // error, declined or an unknown status: release the order
}

http_response_code(200);
```

`statusCode()` returns one of the `Webhook::STATUS_*` constants. It uses the numeric `statusCode` field when present and otherwise maps the textual `status`. An unrecognised status counts as failed, never as paid.

Other fields: `contiPayReference()`, `providerReference()` (e.g. the EcoCash transaction ID), `merchantId()`, `amount()`, `currency()`, `providerCode()`, `providerName()`, `methodCode()`, `message()`, `get($key)` and `toArray()`.

ContiPay can send the same notification more than once, so skip orders you have already settled. Notifications are not signed. Before you release goods, check that `merchantId()` and `amount()` match your order, or confirm the payment with the API. `Webhook` also reads status responses:

```php
$status = new Webhook(json_decode($contipay->getTransactionStatus($webhook->reference()), true));

if ($status->isPaid()) {
    // confirmed by ContiPay
}
```

## Testing

```bash
composer install
composer test
```

The tests use a mocked HTTP client and never contact ContiPay.

## Security

- All API requests are encrypted using TLS
- Sensitive data is never logged
- API credentials are required for all requests

## Additional Resources

- [Support Portal](https://contipay.co.zw)

## 📄 License

This SDK is open-sourced software licensed under the [MIT license](LICENSE.md).