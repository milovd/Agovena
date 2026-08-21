<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.account.nav_orders')],
            ],
        ])

        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.account.orders_title') }}</h1>
        </header>

        @if ($orders->isEmpty())
            <x-ag.empty :title="__('customer.account.no_orders')">
                <x-slot:icon>
                    <x-ag.icon name="shopping-bag" :size="22" />
                </x-slot:icon>
                <x-slot:description>{{ __('customer.account.no_orders_hint') }}</x-slot:description>
                <x-slot:actions>
                    <a class="store-btn store-btn--outline" href="{{ route('storefront.home') }}">{{ __('customer.account.browse_catalog') }}</a>
                </x-slot:actions>
            </x-ag.empty>
        @else
            <div class="store-order-list" role="list">
                @foreach ($orders as $order)
                    <div role="listitem">
                        @include('theme::account.partials.order-card', ['order' => $order])
                    </div>
                @endforeach
            </div>

            <div class="store-account-panel__pagination">
                {{ $orders->links() }}
            </div>
        @endif
    </section>
</div>
