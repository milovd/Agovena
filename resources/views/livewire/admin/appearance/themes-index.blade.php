<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Themes</h2>
            <p class="admin-page__lede">Installed Themes discovered under <code>themes/</code>. Only one Theme is active for the storefront.</p>
        </div>
        <a class="ag-btn" href="{{ route('admin.appearance.customize') }}">Customize active</a>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th>Theme</th>
                    <th>Version</th>
                    <th>Status</th>
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
                                <span class="ag-badge ag-badge--success">Active</span>
                            @else
                                <span class="ag-badge">Installed</span>
                            @endif
                        </td>
                        <td>
                            @if ($theme->id !== $activeId)
                                <button type="button" class="ag-btn ag-btn--primary" wire:click="activate('{{ $theme->id }}')">Activate</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="ag-empty" role="status">
                                <p class="ag-empty__title">No Themes found</p>
                                <p class="ag-empty__text">Add a package under <code>themes/{id}</code> with <code>theme.json</code>.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
