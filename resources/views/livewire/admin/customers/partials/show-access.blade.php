{{-- Customer workspace sidebar: login access card. Part of livewire.admin.customers.show. --}}
<section class="customer-workspace__card" aria-labelledby="access-heading">
    <header class="customer-workspace__section-header">
        <div>
            <p class="customer-workspace__eyebrow">{{ __('admin.customers.access_eyebrow') }}</p>
            <h2 id="access-heading" class="customer-workspace__section-title">{{ __('admin.customers.access_heading') }}</h2>
        </div>
    </header>
    @if ($user)
        <div class="customer-workspace__role-list">
            @forelse (($user->roles ?? collect()) as $role)
                <span class="ag-badge">{{ $role->name }}</span>
            @empty
                <span class="customer-workspace__empty">{{ __('admin.customers.no_roles') }}</span>
            @endforelse
        </div>
        @can('users.update')
            <form class="customer-workspace__roles-form" wire:submit="saveRoles">
                <fieldset class="ag-fieldset">
                    <legend class="ag-fieldset__legend">{{ __('admin.customers.roles') }}</legend>
                    <div class="customer-workspace__role-options">
                        @forelse ($availableRoles as $roleOption)
                            <label class="ag-check" wire:key="customer-role-{{ $roleOption->id }}">
                                <input type="checkbox" value="{{ $roleOption->name }}" wire:model="selectedRoles">
                                <span>{{ $roleOption->name }}</span>
                            </label>
                        @empty
                            <p class="ag-field__help">{{ __('admin.customers.no_roles_available') }}</p>
                        @endforelse
                    </div>
                    @error('selectedRoles') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('selectedRoles.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </fieldset>
                <button class="ag-btn ag-btn--ghost" type="submit">{{ __('admin.customers.save_roles') }}</button>
            </form>
        @endcan
    @else
        <p class="customer-workspace__empty">{{ __('admin.customers.no_user') }}</p>
    @endif
</section>
