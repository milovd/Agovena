<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Appearance;

use App\Agovena\Theme\ThemeManager;
use Livewire\Component;

final class ThemesIndex extends Component
{
    public function activate(string $id, ThemeManager $themes): void
    {
        $this->authorize('theme.manage');
        $themes->activate($id);
        session()->flash('status', 'Theme activated.');
    }

    public function render(ThemeManager $themes)
    {
        $this->authorize('theme.view');

        return view('livewire.admin.appearance.themes-index', [
            'themes' => $themes->all(),
            'activeId' => $themes->active()->id,
        ])->layout('layouts.admin', [
            'title' => 'Themes',
        ]);
    }
}
