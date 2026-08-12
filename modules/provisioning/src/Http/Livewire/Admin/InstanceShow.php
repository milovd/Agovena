<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning\Http\Livewire\Admin;

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Admin\AdminRegistrar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class InstanceShow extends Component
{
    use AuthorizesRequests;

    public ServiceInstance $instance;

    public string $provider_key = '';

    public string $external_ref = '';

    public function mount(ServiceInstance $instance): void
    {
        $this->authorize('provisioning.view');
        $this->instance = $instance->load(['product', 'order']);
        $this->provider_key = (string) $instance->provider_key;
        $this->external_ref = (string) $instance->external_ref;
    }

    public function saveTracking(ProvisioningService $provisioning): void
    {
        $this->authorize('provisioning.manage');
        $this->instance = $provisioning->updateTracking(
            $this->instance,
            $this->provider_key,
            $this->external_ref,
        );
        session()->flash('status', __('provisioning::admin.tracking_saved'));
    }

    public function markProvisioning(ProvisioningService $provisioning): void
    {
        $this->authorize('provisioning.manage');
        $this->instance = $provisioning->markProvisioning($this->instance);
        session()->flash('status', __('provisioning::admin.marked_provisioning'));
    }

    public function activate(ProvisioningService $provisioning): void
    {
        $this->authorize('provisioning.manage');
        $this->instance = $provisioning->activate(
            $this->instance,
            $this->external_ref !== '' ? $this->external_ref : null,
        );
        session()->flash('status', __('provisioning::admin.activated'));
    }

    public function suspend(ProvisioningService $provisioning): void
    {
        $this->authorize('provisioning.manage');
        $this->instance = $provisioning->suspend($this->instance);
        session()->flash('status', __('provisioning::admin.suspended'));
    }

    public function terminate(ProvisioningService $provisioning): void
    {
        $this->authorize('provisioning.manage');
        $this->instance = $provisioning->terminate($this->instance);
        session()->flash('status', __('provisioning::admin.terminated'));
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.provisioning.show', [
            'instance' => $this->instance,
        ])->layout('layouts.admin', [
            'title' => __('provisioning::admin.show_title', ['number' => $this->instance->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
