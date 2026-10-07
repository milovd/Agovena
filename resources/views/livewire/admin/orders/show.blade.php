<div class="admin-page admin-page--order">
    <x-ag.page-header :heading="__('admin.orders.show.title', ['number' => $order->number])" :lede="__('admin.orders.show.lede')">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.orders.title'), 'url' => route('admin.orders.index')],
                ['label' => $order->number],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.index')" :label="__('admin.orders.title')" />
        </x-slot:back>
        <x-slot:actions>
            @can('orders.update')
                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.orders.edit', $order) }}">{{ __('admin.orders.actions.edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--error" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-order-layout">
        <div class="ag-order-layout__main">
            @include('livewire.admin.orders.partials.show-items')

            @include('livewire.admin.orders.partials.show-addresses')

            @if (($order->custom_properties_snapshot ?? []) !== [])
                <section class="ag-section" aria-labelledby="order-properties-heading">
                    <header class="ag-section__header">
                        <h3 id="order-properties-heading" class="ag-section__title">{{ __('admin.customer_properties.values_heading') }}</h3>
                    </header>
                    <div class="ag-section__body">
                        <dl class="ag-dl">
                            @foreach ($order->custom_properties_snapshot as $property)
                                <div>
                                    <dt>{{ $property['label'] ?? $property['key'] }}</dt>
                                    <dd>{{ $property['value'] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif

            @foreach ($orderDetailSections ?? [] as $section)
                @livewire($section->component, ['order' => $order], key($section->id.'-'.$order->id))
            @endforeach
        </div>

        <aside class="ag-order-layout__side">
            @include('livewire.admin.orders.partials.show-summary')

            <section class="ag-section" aria-labelledby="customer-heading">
                <header class="ag-section__header">
                    <h3 id="customer-heading" class="ag-section__title">{{ __('common.customer') }}</h3>
                </header>
                <div class="ag-section__body">
                    <dl class="ag-dl">
                        <div><dt>{{ __('common.name') }}</dt><dd>{{ $order->customer_name }}</dd></div>
                        <div><dt>{{ __('common.email') }}</dt><dd><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></dd></div>
                    </dl>
                </div>
            </section>

            @include('livewire.admin.orders.partials.show-payments')

            @include('livewire.admin.orders.partials.show-documents')
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
