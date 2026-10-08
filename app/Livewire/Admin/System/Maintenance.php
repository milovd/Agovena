<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Maintenance\MaintenanceMode;
use App\Livewire\Concerns\RequiresRecentPassword;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Maintenance extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword {
        confirmRecentPassword as protected confirmRecentPasswordAfterAuthorization;
    }

    private const ENDS_AT_FORMAT = 'Y-m-d\TH:i';

    public string $message = '';

    public string $endsAt = '';

    public function mount(MaintenanceMode $maintenance): void
    {
        $this->authorize(MaintenanceMode::PERMISSION);

        $state = $maintenance->state();
        $this->message = $state->message ?? '';
        // A past end time would fail validation on the next save, so start empty.
        $this->endsAt = $state->endsAt !== null && $state->endsAt->isFuture()
            ? $state->endsAt->timezone($this->timezone())->format(self::ENDS_AT_FORMAT)
            : '';
    }

    /**
     * Turn maintenance on, or save a new message and end time while it is on.
     */
    public function enable(MaintenanceMode $maintenance): void
    {
        $this->authorize(MaintenanceMode::PERMISSION);

        $this->message = MaintenanceMode::normaliseMessage($this->message) ?? '';
        $this->endsAt = trim($this->endsAt);
        $this->validate([
            'message' => ['nullable', 'string', 'max:'.MaintenanceMode::MESSAGE_MAX_LENGTH],
            'endsAt' => ['nullable', 'date_format:'.self::ENDS_AT_FORMAT, 'after:now'],
        ], [], [
            'message' => __('admin.maintenance.message_label'),
            'endsAt' => __('admin.maintenance.ends_at_label'),
        ]);

        if (! $this->requireRecentPassword('enable')) {
            return;
        }

        $wasEnabled = $maintenance->enabled();
        $endsAt = $this->endsAt === ''
            ? null
            : CarbonImmutable::createFromFormat(self::ENDS_AT_FORMAT, $this->endsAt, $this->timezone());

        $maintenance->enable($this->message === '' ? null : $this->message, $endsAt ?: null);

        session()->flash('status', $wasEnabled ? __('admin.maintenance.updated') : __('admin.maintenance.enabled'));
    }

    public function disable(MaintenanceMode $maintenance): void
    {
        $this->authorize(MaintenanceMode::PERMISSION);

        if (! $this->requireRecentPassword('disable')) {
            return;
        }

        $maintenance->disable();

        session()->flash('status', __('admin.maintenance.disabled'));
    }

    public function confirmRecentPassword(): void
    {
        $this->authorize(MaintenanceMode::PERMISSION);
        $this->confirmRecentPasswordAfterAuthorization();
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public function render(AdminRegistrar $admin, MaintenanceMode $maintenance)
    {
        $this->authorize(MaintenanceMode::PERMISSION);

        $state = $maintenance->state();

        return view('livewire.admin.system.maintenance', [
            'state' => $state,
            'enabledAt' => $state->enabledAt?->timezone($this->timezone()),
            'endsAtDisplay' => $state->endsAt?->timezone($this->timezone()),
            'messageMaxLength' => MaintenanceMode::MESSAGE_MAX_LENGTH,
            'storefrontUrl' => url('/'),
        ])->layout('layouts.admin', [
            'title' => __('admin.maintenance.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
