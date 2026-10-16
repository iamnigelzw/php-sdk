<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Support;

/**
 * Canonical list of the wallet providers the SDK can route to.
 *
 * Both acquiring (Mobile, Voucher) and payouts (Disbursement) read from this
 * registry so provider names, codes, types and capabilities only ever live in
 * one place.
 */
final class ProviderRegistry
{
    /** Capability for collecting money from a wallet. */
    public const ACQUIRE = 'acquire';

    /** Capability for sending money to a wallet. */
    public const PAYOUT = 'payout';

    /** Mobile money provider type. */
    public const TYPE_MOBILE = 'mobile';

    /** Voucher provider type. */
    public const TYPE_VOUCHER = 'voucher';

    /**
     * Fields every provider needs before a request is built.
     *
     * @var list<string>
     */
    private const BASE_REQUIRED = ['amount', 'currency', 'phone', 'reference'];

    /**
     * Method name => provider definition.
     *
     * The `mobile` entry is the escape hatch for providers chosen at runtime:
     * it carries no name or code of its own, so the caller supplies both.
     *
     * `currencies` restricts a provider to a known set; omit it to allow any.
     *
     * @var array<string, array{name: string, code: string, type: string, capabilities: list<string>, required?: list<string>, currencies?: list<string>}>
     */
    private const PROVIDERS = [
        'ecocash' => [
            'name' => 'EcoCash',
            'code' => 'EC',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'onemoney' => [
            'name' => 'OneMoney',
            'code' => 'OM',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'omari' => [
            'name' => 'Omari',
            'code' => 'OC',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'innbucks' => [
            'name' => 'InnBucks',
            'code' => 'IB',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'telecash' => [
            'name' => 'TeleCash',
            'code' => 'TC',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE],
        ],
        'mtn' => [
            'name' => 'MTN',
            'code' => 'MN',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'airtel' => [
            'name' => 'AirTel',
            'code' => 'AT',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'zamtel' => [
            'name' => 'ZamTel',
            'code' => 'ZT',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'equitel' => [
            'name' => 'EquiTel',
            'code' => 'ET',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE],
        ],
        'mpesa' => [
            'name' => 'MPesa',
            'code' => 'MP',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
        ],
        'orange' => [
            'name' => 'Orange',
            'code' => 'OG',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE],
        ],
        'tigo' => [
            'name' => 'Tigo',
            'code' => 'TG',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE],
        ],
        'voucher' => [
            'name' => 'Voucher',
            'code' => 'VC',
            'type' => self::TYPE_VOUCHER,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
            'currencies' => ['ZAR', 'USD', 'ZWG', 'ZMW'],
        ],
        'mobile' => [
            'name' => '',
            'code' => '',
            'type' => self::TYPE_MOBILE,
            'capabilities' => [self::ACQUIRE, self::PAYOUT],
            'required' => ['amount', 'currency', 'phone', 'reference', 'provider', 'code'],
        ],
    ];

    /**
     * Whether a provider method exists at all.
     */
    public static function has(string $method): bool
    {
        return isset(self::PROVIDERS[strtolower($method)]);
    }

    /**
     * Whether a provider can be used for the given capability.
     */
    public static function supports(string $method, string $capability): bool
    {
        $provider = self::find($method);

        return $provider !== null && in_array($capability, $provider['capabilities'], true);
    }

    /**
     * Whether a provider accepts the given currency.
     *
     * Providers without a `currencies` list accept anything the API allows.
     */
    public static function supportsCurrency(string $method, string $currency): bool
    {
        $provider = self::find($method);

        if ($provider === null || $provider['currencies'] === null) {
            return true;
        }

        return in_array(strtoupper($currency), $provider['currencies'], true);
    }

    /**
     * Look up a single provider, normalised to always carry `required`,
     * `type` and `currencies` keys.
     *
     * @return array{name: string, code: string, type: string, capabilities: list<string>, required: list<string>, currencies: list<string>|null}|null
     */
    public static function find(string $method): ?array
    {
        $method = strtolower($method);

        if (!isset(self::PROVIDERS[$method])) {
            return null;
        }

        $provider = self::PROVIDERS[$method];
        $provider['required'] = $provider['required'] ?? self::BASE_REQUIRED;
        $provider['currencies'] = $provider['currencies'] ?? null;

        return $provider;
    }

    /**
     * Every provider supporting the given capability, keyed by method name.
     *
     * @param string|null $type Restrict to a provider type, or null for all types.
     * @return array<string, array{name: string, code: string, type: string, capabilities: list<string>, required: list<string>, currencies: list<string>|null}>
     */
    public static function supporting(string $capability, ?string $type = null): array
    {
        $providers = [];

        foreach (array_keys(self::PROVIDERS) as $method) {
            $provider = self::find($method);

            if (!in_array($capability, $provider['capabilities'], true)) {
                continue;
            }

            if ($type !== null && $provider['type'] !== $type) {
                continue;
            }

            $providers[$method] = $provider;
        }

        return $providers;
    }
}
