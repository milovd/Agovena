<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning;

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProvisioningService
{
    public function createFromPaidOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()->with('capabilities')->find($item->product_id);
            if ($product === null || ! $product->hasCapability('provisionable')) {
                continue;
            }

            $exists = ServiceInstance::query()
                ->where('order_item_id', $item->id)
                ->exists();
            if ($exists) {
                continue;
            }

            $capability = $product->capability('provisionable');
            $config = $capability !== null ? ($capability->config ?? []) : [];
            $providerKey = isset($config['provider_key']) && is_string($config['provider_key']) && $config['provider_key'] !== ''
                ? $config['provider_key']
                : null;

            for ($i = 0; $i < $item->quantity; $i++) {
                ServiceInstance::query()->create([
                    'number' => $this->generateNumber(),
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product->id,
                    'customer_id' => $order->customer_id,
                    'customer_email' => $order->customer_email,
                    'customer_name' => $order->customer_name,
                    'subscription_id' => null,
                    'status' => ServiceInstanceStatus::Pending,
                    'provider_key' => $providerKey,
                    'meta' => [
                        'label' => $item->label,
                        'unit_amount' => $item->unit_amount,
                        'currency' => $item->currency,
                    ],
                ]);
            }
        }
    }

    public function markProvisioning(ServiceInstance $instance): ServiceInstance
    {
        if (! in_array($instance->status, [ServiceInstanceStatus::Pending, ServiceInstanceStatus::Failed], true)) {
            throw ValidationException::withMessages([
                'instance' => __('provisioning::errors.cannot_provision'),
            ]);
        }

        $instance->status = ServiceInstanceStatus::Provisioning;
        $instance->provisioning_at = now();
        $instance->failed_at = null;
        $instance->failure_message = null;
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    public function activate(ServiceInstance $instance, ?string $externalRef = null): ServiceInstance
    {
        if (! $instance->canActivate()) {
            throw ValidationException::withMessages([
                'instance' => __('provisioning::errors.cannot_activate'),
            ]);
        }

        $instance->status = ServiceInstanceStatus::Active;
        $instance->activated_at = now();
        $instance->suspended_at = null;
        $instance->failed_at = null;
        $instance->failure_message = null;
        if ($externalRef !== null && $externalRef !== '') {
            $instance->external_ref = $externalRef;
        }
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    public function suspend(ServiceInstance $instance): ServiceInstance
    {
        if (! $instance->canSuspend()) {
            throw ValidationException::withMessages([
                'instance' => __('provisioning::errors.cannot_suspend'),
            ]);
        }

        $instance->status = ServiceInstanceStatus::Suspended;
        $instance->suspended_at = now();
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    public function terminate(ServiceInstance $instance): ServiceInstance
    {
        if (! $instance->canTerminate()) {
            throw ValidationException::withMessages([
                'instance' => __('provisioning::errors.cannot_terminate'),
            ]);
        }

        $instance->status = ServiceInstanceStatus::Terminated;
        $instance->terminated_at = now();
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    public function fail(ServiceInstance $instance, string $message): ServiceInstance
    {
        $instance->status = ServiceInstanceStatus::Failed;
        $instance->failed_at = now();
        $instance->failure_message = $message;
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    public function updateTracking(ServiceInstance $instance, ?string $providerKey, ?string $externalRef): ServiceInstance
    {
        if ($providerKey !== null) {
            $instance->provider_key = $providerKey !== '' ? $providerKey : null;
        }
        if ($externalRef !== null) {
            $instance->external_ref = $externalRef !== '' ? $externalRef : null;
        }
        $instance->save();

        return $instance->fresh() ?? $instance;
    }

    private function generateNumber(): string
    {
        do {
            $number = 'SVC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (ServiceInstance::query()->where('number', $number)->exists());

        return $number;
    }
}
