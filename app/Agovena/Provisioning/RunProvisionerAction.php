<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use App\Agovena\Provisioning\Contracts\ProvisionerActions;
use App\Agovena\Provisioning\Contracts\ResolvesProvisionedServices;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

final class RunProvisionerAction
{
    public function __construct(
        private readonly ProvisionerRegistry $provisioners,
        private readonly ?ResolvesProvisionedServices $services = null,
    ) {}

    public function handle(Customer $customer, int $instanceId, string $actionId): void
    {
        if ($this->services === null) {
            abort(404);
        }

        $info = $this->services->resolveForCustomer($customer, $instanceId);
        if ($info === null) {
            abort(404);
        }

        $provisioner = $info->providerKey !== null
            ? $this->provisioners->get($info->providerKey)
            : null;

        if (! $provisioner instanceof ProvisionerActions) {
            throw ValidationException::withMessages([
                'action' => __('notifications.provisioning.action_unavailable'),
            ]);
        }

        $isAllowed = collect($provisioner->actions($info))
            ->contains(static fn (ProvisionerAction $action): bool => $action->id === $actionId);

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                'action' => __('notifications.provisioning.invalid_action'),
            ]);
        }

        $provisioner->runAction($info, $actionId);
    }
}
