<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\Contracts\ProvisionerActions;
use App\Agovena\Provisioning\Contracts\ProvisionerPanel;
use Illuminate\Validation\ValidationException;

final class ManualProvisioner implements Provisioner, ProvisionerActions, ProvisionerPanel
{
    public function id(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return __('notifications.provisioning.manual');
    }

    public function actions(ServiceInstanceInfo $instance): array
    {
        return [
            new ProvisionerAction('refresh_status', __('notifications.provisioning.refresh_status')),
        ];
    }

    public function runAction(ServiceInstanceInfo $instance, string $actionId): void
    {
        if ($actionId !== 'refresh_status') {
            throw ValidationException::withMessages([
                'action' => __('notifications.provisioning.invalid_action'),
            ]);
        }
    }

    public function panel(ServiceInstanceInfo $instance): ProvisionerPanelData
    {
        return new ProvisionerPanelData(__('notifications.provisioning.details'), [
            ['label' => __('notifications.provisioning.status'), 'value' => $instance->status],
            ['label' => __('notifications.provisioning.reference'), 'value' => $instance->externalRef ?? '-'],
        ]);
    }
}
