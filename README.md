# ContiPay PHP SDK

Accept mobile money and card payments, and send payouts, through [ContiPay](https://contipay.co.zw) from any PHP application.

[![Latest Version](https://img.shields.io/packagist/v/contipay/php-sdk.svg)](https://packagist.org/packages/contipay/php-sdk)
[![License](https://img.shields.io/packagist/l/contipay/php-sdk.svg)](LICENSE.md)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat&labelColor=18181b)](https://whyphp.dev)

```php
$response = $mobile->ecocash([
    'amount'    => '10.00',
    'currency'  => 'USD',
    'phone'     => '0782000340',
    'reference' => 'ORDER-1001',
]);
```

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Key concepts](#key-concepts)
- [Accept payments](#accept-payments)
  - [Hosted checkout](#hosted-checkout) (recommended)
  - [Mobile money](#mobile-money)
  - [Cards](#cards)
- [Send payouts](#send-payouts)
- [Check a transaction's status](#check-a-transactions-status)
- [Webhooks](#webhooks)
- [Handling errors](#handling-errors)
- [Going live checklist](#going-live-checklist)
- [Reference](#reference)
- [Security](#security)
- [Contributing and tests](#contributing-and-tests)

## Installation

Requires PHP 8.1+ with the `json` and `openssl` extensions, and a ContiPay merchant account (API key, API secret and merchant ID).

```bash
composer require contipay/php-sdk
```

> On PHP 7.4 or 8.0, use the 1.x line: `composer require contipay/php-sdk:^1.2`.

## Quick start

Charge an EcoCash wallet in the test environment, then check whether it was paid.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ContiPay\PhpSdk\Mobile;

$mobile = (new Mobile(getenv('CONTIPAY_API_KEY'), getenv('CONTIPAY_API_SECRET'), 'dev'))
    ->setMerchantId((int) getenv('CONTIPAY_MERCHANT_ID'))
    ->setWebhookUrl('https://your-site.com/contipay/webhook');

$reference = 'ORDER-1001'; // your own unique reference, saved against the order

$response = json_decode($mobile->ecocash([
    'amount'    => '10.00',
    'currency'  => 'USD',
    'phone'     => '0782000340',
    'reference' => $reference,
]), true);

// The customer confirms on their phone. ContiPay then notifies your webhook,
// or you can ask for the status yourself:
$status = json_decode($mobile->getTransactionStatus($reference), true);
```

That is the whole flow: **create** a payment with your reference, then **confirm** it by webhook or status lookup before you fulfil the order.

## Key concepts

### Environments

| Mode   | API                            | Use it for                     |
|--------|--------------------------------|--------------------------------|
| `dev`  | `https://api-uat.contipay.net` | Building and testing (default) |
| `live` | `https://api.contipay.net`     | Real money                     |

Pass the mode as the third constructor argument. It is case-insensitive. To point at different hosts, call `updateContipayURL($devUrl, $liveUrl)`.

### Direct vs redirect

`Mobile` and `Card` take a fourth constructor argument, the payment flow:

- **`direct`** (default): you collect the details and ContiPay charges the wallet or card straight away.
- **`redirect`**: ContiPay returns a payment page URL (`redirectUrl`) to send the customer to. Set `setSuccessUrl()` and `setErrorUrl()` for where they come back to.

`Checkout` always uses redirect, and `Disbursement` always uses direct.

### References

Every transaction has a **reference**: your own ID for it, sent to ContiPay as `merchantRef`. It is how you look up status and how you match webhooks to orders, so:

- make it unique per transaction (e.g. your order ID plus an attempt number),
- save it **before** you call ContiPay, so you can recover if the response never arrives.

Where a reference is optional and you leave it out, the SDK generates one like `V-4829104738291045`.

### Amounts

Pass amounts as strings or numbers in major units: `'10.50'` means ten dollars fifty. They must be positive with at most 2 decimal places. `'10'`, `'10.5'`, `10.5` and `10` are all fine. `'-5'`, `'0'`, `'1e3'` and `'1.005'` are rejected before anything is sent.

### Responses

Payment, payout and status methods return ContiPay's response as a **JSON string**. Decode it with `json_decode($response, true)`. If the request could not complete, you get an SDK-built JSON error instead (see [Handling errors](#handling-errors)). `Checkout` is the exception: it returns a `CheckoutSession` object.

## Accept payments

### Hosted checkout

**Recommended for most integrations.** The customer pays on ContiPay's page and picks their own method, so card numbers and wallet PINs never touch your server.

```php
use ContiPay\PhpSdk\Checkout;

$checkout = (new Checkout($apiKey, $apiSecret, 'live'))
    ->setMerchantId($merchantId)
    ->setWebhookUrl('https://your-site.com/contipay/webhook')
    ->setSuccessUrl('https://your-site.com/orders/1001/thanks')
    ->setCancelUrl('https://your-site.com/orders/1001');

$session = $checkout->create([
    'amount'      => '50.00',
    'currency'    => 'USD',
    'phone'       => '0782000340',
    'reference'   => 'ORDER-1001',
    'description' => 'Order #1001',
]);

if ($session->successful()) {
    header('Location: ' . $session->url());
    exit;
}

error_log('ContiPay checkout failed: ' . $session->message());
```

| Field                   | Required | Notes                                              |
|-------------------------|----------|----------------------------------------------------|
| `amount`                | Yes      | See [Amounts](#amounts)                            |
| `currency`              | Yes      | e.g. `USD`, `ZWG`                                  |
| `phone`                 | Yes      | Customer phone number                              |
| `reference`             | No       | Generated if left out                              |
| `description`           | No       | Shown on the payment page                          |
| `firstName`, `lastName` | No       | Defaults to the phone number                       |
| `email`                 | No       |                                                    |
| `country`               | No       | Defaults to `ZW`                                   |
| `cod`, `coc`            | No       | Cash on delivery / collection. Defaults to `false` |

`create()` returns a `CheckoutSession`:

| Method                  | Returns                                   |
|-------------------------|-------------------------------------------|
| `successful()`          | Whether ContiPay returned a payment page  |
| `url()`                 | The payment page URL, or `null`           |
| `reference()`           | The reference to save against the order   |
| `message()`             | ContiPay's message, or the SDK error      |
| `toArray()`, `toJson()` | The full response                         |

> **The success URL is not proof of payment.** Anyone can visit it. Confirm by [webhook](#webhooks) or [status lookup](#check-a-transactions-status). A status lookup made straight after `create()` may return "not found" until the customer pays.

Create the session on your server. A "Pay" button in the browser should link to your own endpoint, which calls `create()` and redirects. It must never call ContiPay directly, because that would expose your API secret.

### Mobile money

```php
use ContiPay\PhpSdk\Mobile;

$mobile = (new Mobile($apiKey, $apiSecret, 'live')) // 4th argument: 'direct' (default) or 'redirect'
    ->setMerchantId($merchantId)
    ->setWebhookUrl('https://your-site.com/contipay/webhook');

$response = $mobile->ecocash([
    'amount'      => '50.00',
    'currency'    => 'USD',
    'phone'       => '0782000340',
    'reference'   => 'ORDER-1001',
    'description' => 'Order #1001', // optional
]);
```

| Method         | Provider                        | Code |
|----------------|---------------------------------|------|
| `ecocash([])`  | EcoCash                         | `EC` |
| `onemoney([])` | OneMoney                        | `OM` |
| `omari([])`    | Omari                           | `OC` |
| `innbucks([])` | InnBucks                        | `IB` |
| `mobile([])`   | Any. Pass `provider` and `code` | —    |

Required for every provider: `amount`, `currency`, `phone`, `reference`. `mobile()` also needs `provider` (e.g. `'EcoCash'`) and `code` (e.g. `'EC'`), which lets you route to a provider chosen at runtime:

```php
$response = $mobile->mobile([
    'amount'    => '50.00',
    'currency'  => 'USD',
    'phone'     => '0782000340',
    'reference' => 'ORDER-1001',
    'provider'  => 'OneMoney',
    'code'      => 'OM',
]);
```

For the redirect flow, construct with `'redirect'` and set `setSuccessUrl()` and `setErrorUrl()`.

### Cards

> **PCI DSS:** with the direct flow, card numbers and CVVs pass through your server, which puts you in full PCI DSS scope. Unless you are PCI compliant, use [Hosted checkout](#hosted-checkout) or `Card` with the `redirect` flow.

```php
use ContiPay\PhpSdk\Card;

$card = (new Card($apiKey, $apiSecret, 'live', 'redirect'))
    ->setMerchantId($merchantId)
    ->setWebhookUrl('https://your-site.com/contipay/webhook')
    ->setSuccessUrl('https://your-site.com/orders/1001/thanks')
    ->setErrorUrl('https://your-site.com/orders/1001');

$response = $card->visa([
    'accountNumber' => '4111111111111111',
    'accountExpiry' => '12/26',
    'cvv'           => '123',
    'phone'         => '0782000340',
    'amount'        => '50.00',
    'currency'      => 'USD',
    'reference'     => 'ORDER-1001',
    // optional
    'firstName'     => 'Tariro',
    'lastName'      => 'Moyo',
    'email'         => 'tariro@example.com',
    'country'       => 'ZW',
    'description'   => 'Order #1001',
]);
```

| Method           | Card       | Code | Required fields                                                                     |
|------------------|------------|------|-------------------------------------------------------------------------------------|
| `visa([])`       | Visa       | `VA` | `amount`, `currency`, `phone`, `accountNumber`, `accountExpiry`, `cvv`, `reference` |
| `mastercard([])` | MasterCard | `MA` | `amount`, `currency`, `phone`, `accountNumber`, `accountExpiry`, `cvv`              |
| `zimswitch([])`  | ZimSwitch  | `ZS` | `amount`, `currency`, `phone`, `reference`                                          |
| `card([])`       | Any        | —    | As `mastercard`, plus `provider` and `code`                                         |

Where `reference` is not required, one is generated if you leave it out. Passing your own is still strongly recommended.

## Send payouts

`Disbursement` sends money from your merchant account to a mobile wallet. Each payout is signed with your merchant RSA private key, which ContiPay verifies.

```php
use ContiPay\PhpSdk\Disbursement;

$payouts = (new Disbursement(
    $apiKey,
    $apiSecret,
    '/secure/path/contipay-private.pem', // a path, or the PEM string itself
    'live'
))
    ->setMerchantId($merchantId)
    ->setWebhookUrl('https://your-site.com/contipay/webhook');

$reference = 'PAYOUT-2026-0042'; // save this before sending

$response = $payouts->ecocash([
    'amount'      => '20.00',
    'currency'    => 'USD',
    'phone'       => '0782000340',
    'reference'   => $reference,
    // optional
    'accountName' => 'Tariro Moyo',
    'firstName'   => 'Tariro',
    'lastName'    => 'Moyo',
    'email'       => 'tariro@example.com',
    'description' => 'Commission payout',
]);
```

Supports `ecocash()`, `onemoney()`, `omari()`, `innbucks()` and `mobile()`, with the same provider codes as [Mobile money](#mobile-money). `amount`, `currency`, `phone` and `reference` are always required.

An invalid key throws an `InvalidArgumentException` when you construct the object, so a bad deployment fails at startup instead of at payout time.

> **Never blindly retry a payout.** If the response has `"outcome": "unknown"`, the money may already have been sent. Check `getDisbursementStatus($reference)` first. See [Handling errors](#handling-errors).

## Check a transaction's status

Every class (`Mobile`, `Card`, `Checkout`, `Disbursement`) can look up a transaction by your reference. Call `setMerchantId()` first, because ContiPay requires it for lookups.

```php
$response = $mobile->getTransactionStatus('ORDER-1001');                // payments
$response = $payouts->getDisbursementStatus('PAYOUT-2026-0042');       // payouts
$response = $payouts->getDisbursementStatus('PAYOUT-2026-0042', 'EC'); // narrowed to a provider
```

Read the result with the same `Webhook` class you use for notifications:

```php
use ContiPay\PhpSdk\Webhook;

$status = new Webhook(json_decode($response, true));

if ($status->isPaid()) {
    // fulfil the order
}
```

The lookups call `GET /acquire/payment` and `GET /disburse/payment` with `merchantRef` and `merchantId` (plus `providerCode` when given).

## Webhooks

ContiPay POSTs a JSON notification to your webhook URL whenever a transaction changes status.

```php
use ContiPay\PhpSdk\Webhook;

$webhook = Webhook::fromRequest(); // or Webhook::fromJson($rawBody)

$order = findOrderByReference($webhook->reference());

if ($order === null || $order->isSettled()) {
    http_response_code(200); // unknown or already handled: acknowledge and stop
    exit;
}

if ($webhook->isPaid()) {
    // Notifications are not signed, so confirm with ContiPay before fulfilling.
    $confirmed = new Webhook(json_decode($mobile->getTransactionStatus($order->reference), true));

    if ($confirmed->isPaid() && round($confirmed->amount(), 2) === round($order->amount, 2)) {
        $order->markPaid();
    }
} elseif ($webhook->isFailed()) {
    $order->markFailed();
}
// isPending(): another notification will follow

http_response_code(200);
```

| Check          | True when the status is                   |
|----------------|-------------------------------------------|
| `isPaid()`     | Paid or confirmed                         |
| `isPending()`  | Pending, queued, approved or submitted    |
| `isRefunded()` | Refunded                                  |
| `isFailed()`   | Error, declined, or anything unrecognised |

An unrecognised status always counts as failed, never as paid. `statusCode()` gives the raw `Webhook::STATUS_*` value.

Fields: `reference()`, `contiPayReference()`, `providerReference()` (e.g. the EcoCash transaction ID), `merchantId()`, `amount()`, `currency()`, `providerCode()`, `providerName()`, `methodCode()`, `message()`, plus `get($key)` and `toArray()` for anything else.

**Webhook rules of thumb**

- **Confirm before fulfilling.** Notifications are not signed, so anyone who finds your URL can post one.
- **Be idempotent.** ContiPay may send the same notification more than once.
- **Respond quickly with `200`.** Do slow work (emails, fulfilment) in a queue.

## Handling errors

Errors come in two kinds.

**1. Exceptions: a mistake in your code.** These are thrown before anything is sent to ContiPay, so fix the code rather than catching them.

| Exception                  | When                                                                                        |
|----------------------------|---------------------------------------------------------------------------------------------|
| `InvalidArgumentException` | A required field is missing, the [amount](#amounts) is invalid, or the payout private key can't be read |
| `LogicException`           | A request was made before `setMerchantId()`                                                 |
| `BadMethodCallException`   | The class has no such provider, e.g. `$mobile->paypal()`                                    |

**2. JSON errors: the request did not succeed.** API rejections come back as ContiPay's own JSON. Network and server failures come back as SDK-built JSON:

```json
{ "status": "error", "message": "cURL error 28: Operation timed out", "outcome": "unknown" }
```

`outcome` tells you whether it is safe to try again:

| `outcome`   | Meaning                                                                                       | What to do                       |
|-------------|-----------------------------------------------------------------------------------------------|----------------------------------|
| `"failed"`  | ContiPay never received the request (e.g. connection refused, DNS failure) or rejected it    | Safe to retry                    |
| `"unknown"` | It may have been processed (e.g. timed out after sending, 5xx from a gateway)                 | **Check status first**           |

```php
$result = json_decode($response, true);

if (($result['outcome'] ?? null) === 'unknown') {
    $status = new Webhook(json_decode($mobile->getTransactionStatus($reference), true));
    // isPaid(): treat as success. isPending(): wait for the webhook.
    // Still not found after a short wait: retry.
}
```

The SDK waits 10 seconds to connect and 60 seconds for a response.

## Going live checklist

- [ ] Live API key, secret and merchant ID, loaded from environment variables or a secrets manager rather than committed to code
- [ ] Mode set to `'live'`
- [ ] Webhook URL is public, served over HTTPS, and returns `200` quickly
- [ ] Every transaction has a unique reference, saved **before** calling ContiPay
- [ ] Orders are fulfilled only after a webhook **confirmed with a status lookup**, never on the success URL alone
- [ ] Responses with `"outcome": "unknown"` trigger a status check, not a blind retry
- [ ] Webhook handler is idempotent (ignores already-settled orders)
- [ ] Payout private key is stored outside the web root, with restricted file permissions
- [ ] Cards go through hosted checkout or the redirect flow, unless you are PCI DSS compliant

## Reference

### Constructors

| Class          | Signature                                                            |
|----------------|----------------------------------------------------------------------|
| `Mobile`       | `new Mobile($apiKey, $apiSecret, $mode = 'dev', $method = 'direct')` |
| `Card`         | `new Card($apiKey, $apiSecret, $mode = 'dev', $method = 'direct')`   |
| `Checkout`     | `new Checkout($apiKey, $apiSecret, $mode = 'dev')`                   |
| `Disbursement` | `new Disbursement($apiKey, $apiSecret, $privateKey, $mode = 'dev')`  |
| `Webhook`      | `Webhook::fromRequest()`, `Webhook::fromJson($json)`, `new Webhook($array)` |

### Setters (chainable)

| Method                           | Available on                 | Purpose                                  |
|----------------------------------|------------------------------|------------------------------------------|
| `setMerchantId(int)`             | all                          | Your ContiPay merchant ID (**required**) |
| `setWebhookUrl(string)`          | all                          | Where ContiPay sends notifications       |
| `setSuccessUrl(string)`          | `Mobile`, `Card`, `Checkout` | Return URL after paying (redirect flows) |
| `setErrorUrl(string)`            | `Mobile`, `Card`             | Return URL after a failure or cancel     |
| `setCancelUrl(string)`           | `Checkout`                   | Return URL after cancelling              |
| `updateContipayURL($dev, $live)` | all                          | Override the API hosts                   |

Each setter has a matching getter, e.g. `getMerchantId()`.

## Security

- Keep your API secret and private key on the server. Never ship them to a browser or mobile app.
- All traffic uses HTTPS with certificate verification on. Don't point `updateContipayURL()` at a plain `http://` host.
- The SDK does not log requests or responses. If you log them yourself, strip card numbers, CVVs and credentials first.
- Report security issues privately to [info@contipay.co.zw](mailto:info@contipay.co.zw), not in a public issue.

## Contributing and tests

```bash
composer install
composer test
```

The test suite uses a mocked HTTP client and never contacts ContiPay.

## Support

ContiPay: [contipay.co.zw](https://contipay.co.zw) · [info@contipay.co.zw](mailto:info@contipay.co.zw)

## License

MIT. See [LICENSE.md](LICENSE.md).
