<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Provisioning\Contracts\ProvisionerActions;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

final class RunProvisionerAction
{
    public function __construct(private readonly ProvisionerRegistry $provisioners) {}

    public function handle(Customer $customer, int $instanceId, string $actionId): void
    {
        $instance = ServiceInstance::query()
            ->whereKey($instanceId)
            ->where(function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id)
                    ->orWhere(function ($query) use ($customer): void {
                        $query->whereNull('customer_id')->where('customer_email', $customer->email);
                    });
            })
            ->firstOrFail();

        $provisioner = $instance->provider_key !== null
            ? $this->provisioners->get($instance->provider_key)
            : null;

        if (! $provisioner instanceof ProvisionerActions) {
            throw ValidationException::withMessages([
                'action' => __('notifications.provisioning.action_unavailable'),
            ]);
        }

        $info = self::info($instance);
        $isAllowed = collect($provisioner->actions($info))
            ->contains(static fn (ProvisionerAction $action): bool => $action->id === $actionId);

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                'action' => __('notifications.provisioning.invalid_action'),
            ]);
        }

        $provisioner->runAction($info, $actionId);
    }

    public static function info(ServiceInstance $instance): ServiceInstanceInfo
    {
        return new ServiceInstanceInfo(
            id: $instance->id,
            label: (string) ($instance->meta['label'] ?? $instance->number),
            status: $instance->status->value,
            providerKey: $instance->provider_key,
            externalRef: $instance->external_ref,
            meta: $instance->meta ?? [],
        );
    }
}
