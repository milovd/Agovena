<div class="admin-page">
    <x-ag.page-header :heading="__('admin.modules.title')" :lede="__('admin.modules.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @can('modules.manage')
        @include('livewire.admin.partials.package-install-form', ['kind' => 'module'])
    @endcan

    @if ($modules === [])
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.modules.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.modules.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.modules.column_name') }}</th>
                        <th scope="col">{{ __('admin.modules.column_version') }}</th>
                        <th scope="col">{{ __('admin.packages.column_source') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col">{{ __('admin.modules.column_description') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $row)
                        @php
                            /** @var \App\Agovena\Modules\ModuleManifest $manifest */
                            $manifest = $row['manifest'];
                        @endphp
                        <tr wire:key="module-{{ $manifest->id }}">
                            <td>
                                <div class="ag-table__primary">
                                    <span class="ag-table__name">{{ $manifest->name }}</span>
                                    <span class="ag-muted">{{ $manifest->id }}</span>
                                </div>
                            </td>
                            <td>{{ $manifest->version }}</td>
                            <td>{{ __('admin.packages.source.'.$row['source']->value) }}</td>
                            <td>
                                {{ __($row['lifecycle']->labelKey()) }}
                                @if (! $row['compatible'])
                                    <span class="ag-field__error">{{ $row['compatibility_error'] }}</span>
                                @endif
                            </td>
                            <td>{{ $manifest->description }}</td>
                            <td>
                                <div class="ag-table__actions">
                                    @can('modules.manage')
                                        @if (! $row['installed'] && $row['compatible'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="install('{{ $manifest->id }}')">
                                                {{ __('admin.modules.actions.install') }}
                                            </button>
                                        @endif
                                        @if ($row['enabled'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="disable('{{ $manifest->id }}')">
                                                {{ __('admin.modules.actions.disable') }}
                                            </button>
                                        @elseif ($row['compatible'])
                                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="enable('{{ $manifest->id }}')">
                                                {{ __('admin.modules.actions.enable') }}
                                            </button>
                                        @endif
                                        @if ($row['lifecycle']->value === 'update_available')
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="updatePackage('{{ $manifest->id }}')">
                                                {{ __('admin.packages.actions.update') }}
                                            </button>
                                        @endif
                                        @if ($row['installed'] && ! $row['enabled'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="uninstallPackage('{{ $manifest->id }}')">
                                                {{ __('admin.packages.actions.uninstall') }}
                                            </button>
                                        @endif
                                        @if ($row['can_purge'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}">
                                                {{ __('admin.packages.actions.purge') }}
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ag-muted">{{ __('admin.modules.disable_preserves_data') }}</p>
        <p class="ag-muted">{{ __('admin.packages.uninstall_vs_purge') }}</p>
    @endif
</div>
