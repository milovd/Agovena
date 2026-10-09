<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\CartRequirementContributor;
use App\Agovena\Extensions\ExtensionContext;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Shipping\ShippingCarrierRegistry;

test('extensions can register admin navigation without patching core', function () {
    $context = extensionPluginContext();

    $context->admin()->navigation(new NavigationItem(
        id: 'invoice-layout',
        label: 'admin.nav.invoice_layout',
        group: 'admin.nav_groups.configuration',
        href: '/admin/invoice-layout',
        sort: 80,
    ));

    $ids = collect(app(AdminRegistrar::class)->navigationItems())->pluck('id');

    expect($ids)->toContain('invoice-layout');
});

test('extensions can contribute cart requirements through a public seam', function () {
    $context = extensionPluginContext();

    $context->cartRequirements(new class implements CartRequirementContributor
    {
        public function contribute(CartService $cart): array
        {
            return [CartRequirement::ShippingAddress];
        }
    });

    $requirements = app(CartRequirementComposer::class)->compose(app(CartService::class));

    expect($requirements->has(CartRequirement::ShippingAddress))->toBeTrue();
});

test('invoice documents use the core invoice design and can be overridden by an extension', function () {
    $view = app(InvoiceDocumentView::class);

    expect($view->name())->toBe('invoices.document')
        ->and($view->themeOverride())->toBeNull()
        ->and(view()->exists($view->name()))->toBeTrue();

    $context = extensionPluginContext();
    $context->invoiceDocument('invoice-layout::document');

    expect($view->name())->toBe('invoice-layout::document');
});

function extensionPluginContext(): ExtensionContext
{
    return new ExtensionContext(
        'invoice-layout',
        app(AdminRegistrar::class),
        app(PaymentGatewayRegistry::class),
        app(ProvisionerRegistry::class),
        app(ShippingCarrierRegistry::class),
        app(ExtensionSettingsRepository::class),
        app(CartRequirementComposer::class),
        app(InvoiceDocumentView::class),
    );
}
