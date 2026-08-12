<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Auth;

use Livewire\Component;

final class Login extends Component
{
    public function mount(): void
    {
        $this->redirect(route('login'), navigate: false);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
