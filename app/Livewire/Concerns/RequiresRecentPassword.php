<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait RequiresRecentPassword
{
    public bool $showingPasswordConfirmation = false;

    public string $recentPassword = '';

    public ?string $pendingPasswordAction = null;

    /** @var array<string, mixed> */
    public array $pendingPasswordArgs = [];

    /**
     * @param  array<string, mixed>  $args
     */
    protected function requireRecentPassword(string $action, array $args = []): bool
    {
        if (app(ConfirmsRecentPassword::class)->confirmed()) {
            return true;
        }

        $this->pendingPasswordAction = $action;
        $this->pendingPasswordArgs = $args;
        $this->showingPasswordConfirmation = true;
        $this->recentPassword = '';

        return false;
    }

    public function confirmRecentPassword(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->validate([
            'recentPassword' => ['required', 'string'],
        ]);

        if (! app(ConfirmsRecentPassword::class)->confirm($user, $this->recentPassword)) {
            throw ValidationException::withMessages([
                'recentPassword' => __('admin.security.password_invalid'),
            ]);
        }

        $action = $this->pendingPasswordAction;
        $args = $this->pendingPasswordArgs;
        $this->cancelPasswordConfirmation();

        if (is_string($action) && $action !== '' && $action !== 'confirmRecentPassword' && method_exists($this, $action)) {
            app()->call([$this, $action], $args);
        }
    }

    public function cancelPasswordConfirmation(): void
    {
        $this->showingPasswordConfirmation = false;
        $this->recentPassword = '';
        $this->pendingPasswordAction = null;
        $this->pendingPasswordArgs = [];
        $this->resetValidation('recentPassword');
    }
}
