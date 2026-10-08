{{-- Shown to staff who bypass storefront maintenance. $maintenanceNotice comes from Core. --}}
<div class="store-maintenance-notice" role="status" data-maintenance-notice>
    <p class="store-maintenance-notice__text">{{ __('storefront.maintenance_notice.text') }}</p>
    @if (! empty($maintenanceNotice['manageUrl']))
        <a class="store-maintenance-notice__link" href="{{ $maintenanceNotice['manageUrl'] }}">{{ __('storefront.maintenance_notice.manage') }}</a>
    @endif
</div>
