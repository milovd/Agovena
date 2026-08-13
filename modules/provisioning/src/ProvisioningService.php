<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning;

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Notifications\SendsCataloguedMail;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

            $subscriptionId = null;
            if (Schema::hasTable('subscriptions')) {
                $subscriptionId = DB::table('subscriptions')->where('order_item_id', $item->id)->value('id');
                $subscriptionId = is_numeric($subscriptionId) ? (int) $subscriptionId : null;
            }

            for ($i = 0; $i < $item->quantity; $i++) {
                ServiceInstance::query()->create([
                    'number' => $this->generateNumber(),
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product->id,
                    'customer_id' => $order->customer_id,
                    'customer_email' => $order->customer_email,
                    'customer_name' => $order->customer_name,
                    'subscription_id' => $subscriptionId,
                    'status' => ServiceInstanceStatus::Pending,
                    'provider_key' => $providerKey,
                    'meta' => [
                        'label' => $item->label,
                        'unit_amount' => $item->unit_amount,
                        'currency' => $item->currency,
                        'options_snapshot' => $item->options_snapshot ?? [],
                        'provider_settings' => is_array($config['provider_settings'] ?? null) ? $config['provider_settings'] : [],
                    ],
                ]);
            }
        }

        $this->provisionPendingForOrder($order);
    }

    public function provisionPendingForOrder(Order $order): void
    {
        $orchestrator = app(ProvisioningOrchestrator::class);
        $pending = ServiceInstance::query()
            ->where('order_id', $order->id)
            ->where('status', ServiceInstanceStatus::Pending)
            ->get();

        foreach ($pending as $instance) {
            $orchestrator->provision($instance);
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

        return $this->notifyLifecycle($instance->fresh() ?? $instance, 'service_activated');
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

        return $this->notifyLifecycle($instance->fresh() ?? $instance, 'service_suspended');
    }

    public function unsuspend(ServiceInstance $instance): ServiceInstance
    {
        if ($instance->status !== ServiceInstanceStatus::Suspended) {
            throw ValidationException::withMessages([
                'instance' => __('provisioning::errors.cannot_unsuspend'),
            ]);
        }

        $instance->status = ServiceInstanceStatus::Active;
        $instance->suspended_at = null;
        $instance->save();

        return $this->notifyLifecycle($instance->fresh() ?? $instance, 'service_activated');
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

    private function notifyLifecycle(ServiceInstance $instance, string $key): ServiceInstance
    {
        $route = $key === 'service_activated' || $key === 'service_suspended'
            ? (Route::has('customer.services.show')
                ? route('customer.services.show', $instance)
                : url('/'))
            : url('/');

        app(SendsCataloguedMail::class)->toOrderCustomer(
            $instance->customer_id,
            (string) $instance->customer_email,
            $key,
            [
                'name' => (string) ($instance->customer_name ?? $instance->customer_email),
                'number' => $instance->number,
                'detail' => $instance->number,
                'action_url' => $route,
                'action_label' => __('notifications.'.$key.'.action'),
            ],
        );

        return $instance;
    }
}
