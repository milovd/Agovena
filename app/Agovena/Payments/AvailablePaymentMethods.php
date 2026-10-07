<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersCheckoutMethods;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Models\ExtensionSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Checkout-facing discovery of enabled PaymentGateway methods.
 * Account balance is offered separately in checkout UI (not a gateway).
 *
 * Gateway health can call the provider, so the result is reused within a request
 * and cached briefly. The cache key changes as soon as the gateway settings change.
 */
final class AvailablePaymentMethods
{
    private const HEALTH_TTL_SECONDS = 300;

    /** @var array<string, bool> */
    private array $health = [];

    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /**
     * @return list<string>
     */
    public function ids(?string $country = null): array
    {
        return array_map(static fn (array $option): string => $option['id'], $this->options($country));
    }

    /**
     * @return list<array{id: string, label: string, gateway_id?: string, icon?: string|null, metadata?: array<string, mixed>}>
     */
    public function options(?string $country = null): array
    {
        $options = [];
        foreach ($this->gateways->all() as $gateway) {
            if (! $this->isHealthy($gateway)) {
                continue;
            }

            if ($gateway instanceof OffersCheckoutMethods) {
                foreach ($gateway->checkoutMethods() as $method) {
                    $options[] = $method->toArray();
                }

                continue;
            }

            $options[] = [
                'id' => $gateway->id(),
                'gateway_id' => $gateway->id(),
                'label' => $gateway->label(),
                'icon' => null,
            ];
        }

        if ($options === [] && $this->developmentPayAllowed()) {
            $development = app(DevelopmentPaymentGateway::class);
            $options[] = [
                'id' => $development->id(),
                'gateway_id' => $development->id(),
                'label' => $development->label(),
                'icon' => null,
            ];
        }

        return array_values(array_filter(
            $options,
            fn (array $option): bool => $this->isAvailableInCountry($option, $country),
        ));
    }

    private function isHealthy(PaymentGateway $gateway): bool
    {
        $id = $gateway->id();
        if (array_key_exists($id, $this->health)) {
            return $this->health[$id];
        }

        $settings = ExtensionSetting::query()
            ->where('extension_id', $id)
            ->selectRaw('count(*) as setting_count, max(updated_at) as last_updated_at')
            ->first();
        $fingerprint = sha1($id.'|'.($settings?->getAttribute('setting_count') ?? 0).'|'.($settings?->getAttribute('last_updated_at') ?? ''));

        return $this->health[$id] = (bool) Cache::remember(
            'agovena.payments.gateway_health.'.$fingerprint,
            self::HEALTH_TTL_SECONDS,
            static function () use ($gateway): bool {
                try {
                    return $gateway->health()->ok;
                } catch (Throwable) {
                    return false;
                }
            },
        );
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function isAvailableInCountry(array $option, ?string $country): bool
    {
        $country = strtoupper(trim((string) $country));
        if ($country === '') {
            return true;
        }

        $metadata = $option['metadata'] ?? [];
        $supportedCountries = is_array($metadata) ? ($metadata['customer_countries'] ?? []) : [];
        if (! is_array($supportedCountries) || $supportedCountries === []) {
            return true;
        }

        return in_array($country, array_map('strtoupper', array_map('strval', $supportedCountries)), true);
    }

    private function developmentPayAllowed(): bool
    {
        return (bool) config('agovena.payments.allow_development_instant_pay')
            && ! app()->environment('production');
    }
}
