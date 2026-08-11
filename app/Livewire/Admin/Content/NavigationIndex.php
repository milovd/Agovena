<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class NavigationIndex extends Component
{
    public string $selectedHandle = 'header';

    public string $label = '';

    public string $type = 'url';

    public string $url = '';

    public ?int $page_id = null;

    public ?int $category_id = null;

    public function mount(): void
    {
        $this->ensureMenus();
    }

    public function selectMenu(string $handle): void
    {
        $this->selectedHandle = $handle;
        $this->resetItemForm();
    }

    public function addItem(): void
    {
        $this->authorize('navigation.manage');
        $menu = Menu::query()->where('handle', $this->selectedHandle)->firstOrFail();

        $data = $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['url', 'page', 'category'])],
            'url' => ['nullable', 'required_if:type,url', 'string', 'max:500'],
            'page_id' => ['nullable', 'required_if:type,page', 'exists:pages,id'],
            'category_id' => ['nullable', 'required_if:type,category', 'exists:categories,id'],
        ]);

        $sort = (int) $menu->allItems()->max('sort') + 1;

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'label' => $data['label'],
            'type' => $data['type'],
            'url' => $data['type'] === 'url' ? $data['url'] : null,
            'page_id' => $data['type'] === 'page' ? $data['page_id'] : null,
            'category_id' => $data['type'] === 'category' ? $data['category_id'] : null,
            'sort' => $sort,
        ]);

        $this->resetItemForm();
        session()->flash('status', 'Menu item added.');
    }

    public function deleteItem(int $id): void
    {
        $this->authorize('navigation.manage');
        MenuItem::query()->whereKey($id)->delete();
        session()->flash('status', 'Menu item removed.');
    }

    public function render()
    {
        $this->authorize('navigation.view');
        $this->ensureMenus();

        $menu = Menu::query()
            ->where('handle', $this->selectedHandle)
            ->with(['allItems.page', 'allItems.category'])
            ->firstOrFail();

        return view('livewire.admin.content.navigation-index', [
            'menu' => $menu,
            'menus' => Menu::query()->orderBy('name')->get(),
            'pages' => Page::query()->orderBy('title')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
        ])->layout('layouts.admin', [
            'title' => 'Navigation',
        ]);
    }

    private function ensureMenus(): void
    {
        foreach ([
            'header' => 'Header',
            'footer' => 'Footer',
            'footer_legal' => 'Footer legal',
        ] as $handle => $name) {
            Menu::query()->firstOrCreate(['handle' => $handle], ['name' => $name]);
        }
    }

    private function resetItemForm(): void
    {
        $this->label = '';
        $this->type = 'url';
        $this->url = '';
        $this->page_id = null;
        $this->category_id = null;
    }
}
