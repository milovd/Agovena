<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning\Http\Livewire\Customer;

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Provisioning\Contracts\ProvisionerActions;
use App\Agovena\Provisioning\Contracts\ProvisionerPanel;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\RunProvisionerAction;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Livewire\Component;

final class ServicesIndex extends Component
{
    public function runAction(
        int $instanceId,
        string $actionId,
        RunProvisionerAction $runner,
    ): void {
        /** @var Customer $customer */
        $customer = authenticated_customer();

        $runner->handle($customer, $instanceId, $actionId);
        session()->flash('status', __('provisioning::customer.action_completed'));
    }

    public function render(ThemeManager $themes)
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();

        $instances = ServiceInstance::query()
            ->with('product')
            ->where(function ($q) use ($customer): void {
                $q->where('customer_id', $customer->id)
                    ->orWhere('customer_email', $customer->email);
            })
            ->orderByDesc('id')
            ->get();

        $theme = $themes->active();
        $registry = app(ProvisionerRegistry::class);
        $providerData = [];

        foreach ($instances as $instance) {
            $provisioner = $instance->provider_key !== null ? $registry->get($instance->provider_key) : null;
            $info = RunProvisionerAction::info($instance);
            $providerData[$instance->id] = [
                'panel' => $provisioner instanceof ProvisionerPanel ? $provisioner->panel($info) : null,
                'actions' => $provisioner instanceof ProvisionerActions ? $provisioner->actions($info) : [],
            ];
        }

        return view($theme->view('account.services'), [
            'theme' => $theme,
            'instances' => $instances,
            'providerData' => $providerData,
            'accountSection' => 'services',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('provisioning::customer.title'),
            'theme' => $theme,
        ]);
    }
}
