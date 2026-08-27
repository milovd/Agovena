<div class="admin-page">
    <x-ag.page-header :heading="__('admin.customers.title')" :lede="__('admin.customers.lede')">
        <x-slot:actions>
            @can('users.create')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="createUser">{{ __('admin.customers.add_user') }}</button>
            @endcan
            @can('customers.manage')
                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.customers.properties') }}">{{ __('admin.customer_properties.title') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if ($showUserForm)
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.customers.new_user') }}</h2>
            <form wire:submit="saveUser" class="ag-form" novalidate>
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-user-name">{{ __('common.name') }}</label>
                        <input id="customer-user-name" class="ag-input" type="text" wire:model="userName" required autocomplete="name">
                        @error('userName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-user-email">{{ __('common.email') }}</label>
                        <input id="customer-user-email" class="ag-input" type="email" wire:model="userEmail" required autocomplete="username">
                        @error('userEmail') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-user-password">{{ __('common.password') }}</label>
                        <input id="customer-user-password" class="ag-input" type="password" wire:model="userPassword" required autocomplete="new-password">
                        @error('userPassword') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-user-role">{{ __('admin.customers.roles') }}</label>
                        <select id="customer-user-role" class="ag-select" wire:model="userRole" required>
                            @foreach ($roles as $roleOption)
                                <option value="{{ $roleOption->name }}">{{ $roleOption->name }}</option>
                            @endforeach
                        </select>
                        <p class="ag-field__help">{{ __('admin.customers.role_help') }}</p>
                        @error('userRole') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('admin.customers.add_user') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelUser">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="customer-search">{{ __('common.search') }}</label>
                <input
                    id="customer-search"
                    class="ag-input ag-input--search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('admin.customers.search_placeholder') }}"
                >
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="customer-status">{{ __('common.status') }}</label>
                <select id="customer-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.customers.status_all') }}</option>
                    <option value="active">{{ __('admin.customers.status_active') }}</option>
                    <option value="deletion">{{ __('admin.customers.deletion_requested_badge') }}</option>
                    <option value="anonymized">{{ __('admin.customers.anonymized_badge') }}</option>
                </select>
            </div>
        </div>
    </div>

    @if ($customers->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search || $status ? __('admin.customers.empty_filtered_title') : __('admin.customers.empty_title') }}</p>
            <p class="ag-empty__text">{{ $search || $status ? __('admin.customers.empty_filtered_text') : __('admin.customers.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.customers.customer_column') }}</th>
                        <th scope="col">{{ __('admin.orders.title') }}</th>
                        <th scope="col">{{ __('admin.customers.spent_column') }}</th>
                        <th scope="col">{{ __('admin.customers.credit_heading') }}</th>
                        <th scope="col">{{ __('admin.customers.roles') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col">{{ __('common.created') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        @php
                            $initials = collect(preg_split('/\s+/', trim((string) $customer->name)) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn (string $part): string => mb_substr($part, 0, 1))
                                ->implode('');
                            if ($initials === '') {
                                $initials = 'C';
                            }
                            $creditCurrency = (string) ($customer->creditAccount?->currency ?? 'EUR');
                            $creditBalance = (int) ($customer->creditAccount?->balance_minor ?? 0);
                        @endphp
                        <tr wire:key="customer-row-{{ $customer->id }}">
                            <td>
                                <div class="ag-identity">
                                    <span class="ag-identity__avatar" aria-hidden="true">{{ $initials }}</span>
                                    <div class="ag-identity__text">
                                        <span class="ag-identity__name">
                                            <a href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a>
                                        </span>
                                        <span class="ag-identity__meta">{{ $customer->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ number_format((int) $customer->orders_count) }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format((int) ($customer->paid_orders_total ?? 0), $creditCurrency) }}</td>
                            <td>{{ \App\Support\MoneyFormatter::format($creditBalance, $creditCurrency) }}</td>
                            <td>
                                @forelse (($customer->user?->roles ?? collect()) as $role)
                                    <span class="ag-badge">{{ $role->name }}</span>
                                @empty
                                    <span class="ag-muted">{{ __('admin.customers.no_roles') }}</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($customer->anonymized_at)
                                    <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
                                @elseif ($customer->deletion_requested_at)
                                    <span class="ag-badge ag-badge--warning">{{ __('admin.customers.deletion_requested_badge') }}</span>
                                @else
                                    <span class="ag-badge ag-badge--success">{{ __('admin.customers.status_active') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="ag-muted" title="{{ $customer->created_at?->toDateTimeString() }}">
                                    {{ $customer->created_at?->toFormattedDateString() }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <a
                                    class="ag-icon-btn"
                                    href="{{ route('admin.customers.show', $customer) }}"
                                    title="{{ __('common.view') }}"
                                    aria-label="{{ __('admin.customers.open_aria', ['name' => $customer->name]) }}"
                                >
                                    <x-ag.icon name="external-link" :size="16" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
    @endif
</div>
