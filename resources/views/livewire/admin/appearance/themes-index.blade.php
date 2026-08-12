<div class="admin-page">
    <x-ag.page-header :heading="__('admin.appearance.themes.title')" :lede="__('admin.appearance.themes.lede')">
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.customize') }}">{{ __('admin.appearance.themes.customize_active') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th>{{ __('admin.appearance.themes.theme') }}</th>
                    <th>{{ __('admin.appearance.themes.version') }}</th>
                    <th>{{ __('common.status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($themes as $theme)
                    <tr wire:key="theme-{{ $theme->id }}">
                        <td>
                            <strong>{{ $theme->name }}</strong>
                            <div class="ag-muted">{{ $theme->description }}</div>
                        </td>
                        <td>{{ $theme->version }}</td>
                        <td>
                            @if ($theme->id === $activeId)
                                <span class="ag-badge ag-badge--success">{{ __('admin.appearance.themes.active') }}</span>
                            @else
                                <span class="ag-badge">{{ __('admin.appearance.themes.installed') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($theme->id !== $activeId)
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="activate('{{ $theme->id }}')">{{ __('admin.appearance.themes.activate') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="ag-empty" role="status">
                                <p class="ag-empty__title">{{ __('admin.appearance.themes.empty_title') }}</p>
                                <p class="ag-empty__text">
                                    {!! __('admin.appearance.themes.empty_text', [
                                        'path' => '<code>themes/{id}</code>',
                                        'file' => '<code>theme.json</code>',
                                    ]) !!}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
