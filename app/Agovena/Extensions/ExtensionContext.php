<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\CartRequirementContributor;
use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Shipping\Contracts\ShippingCarrier;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use Closure;

/**
 * Agovena-owned registration surface for Extensions.
 * Intentionally free of Livewire/BEM. Extensions plug into public seams:
 * Admin tabs/pages/settings, cart/checkout requirements, invoice presentation, and gateways.
 */
final class ExtensionContext
{
    /** @var list<ExtensionSettingDefinition> */
    private array $settingDefinitions = [];

    /** @var (Closure(): HealthResult)|null */
    private $healthCallback = null;

    public function __construct(
        private readonly string $extensionId,
        private readonly AdminRegistrar $admin,
        private readonly PaymentGatewayRegistry $paymentGateways,
        private readonly ProvisionerRegistry $provisioners,
        private readonly ShippingCarrierRegistry $shippingCarriers,
        private readonly ExtensionSettingsRepository $settings,
        private readonly CartRequirementComposer $cartRequirements,
        private readonly InvoiceDocumentView $invoiceDocumentView,
    ) {}

    public function extensionId(): string
    {
        return $this->extensionId;
    }

    public function admin(): AdminRegistrar
    {
        return $this->admin;
    }

    public function settings(): ExtensionSettingsRepository
    {
        return $this->settings;
    }

    public function setting(ExtensionSettingDefinition $definition): void
    {
        $this->settingDefinitions[] = $definition;
    }

    /**
     * @return list<ExtensionSettingDefinition>
     */
    public function settingDefinitions(): array
    {
        return $this->settingDefinitions;
    }

    public function paymentGateway(PaymentGateway $gateway): void
    {
        $this->paymentGateways->register($gateway);
    }

    public function provisioner(Provisioner $provisioner): void
    {
        $this->provisioners->register($provisioner);
    }

    public function shippingCarrier(ShippingCarrier $carrier): void
    {
        $this->shippingCarriers->register($carrier);
    }

    public function cartRequirements(CartRequirementContributor $contributor): void
    {
        $this->cartRequirements->add($contributor);
    }

    public function invoiceDocument(string $view): void
    {
        $this->invoiceDocumentView->use($view);
    }

    /**
     * @param  Closure(): HealthResult  $callback
     */
    public function health(Closure $callback): void
    {
        $this->healthCallback = $callback;
    }

    /**
     * @return (Closure(): HealthResult)|null
     */
    public function healthCallback(): ?Closure
    {
        return $this->healthCallback;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($this->extensionId, $key, $default);
    }
}
