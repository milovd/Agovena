<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use App\Agovena\Extensions\ExtensionConfigurationStatus;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisioningServers;
use App\Models\ProvisioningServer;
use Throwable;

/**
 * Answers offline whether a named provisioning server is ready to use: its provider is
 * registered and every connection setting the provider declares as required is filled
 * on the server. Providers without server connections fall back to their Extension
 * settings. Results are cached for the current request; no values leave this class.
 */
final class ProvisioningServerConfiguration
{
    /** @var array<int, bool> */
    private array $resolved = [];

    public function __construct(
        private readonly ProvisionerRegistry $provisioners,
        private readonly ExtensionConfigurationStatus $extensions,
    ) {}

    public function isConfigured(ProvisioningServer $server): bool
    {
        return $this->resolved[$server->id] ??= $this->resolve($server);
    }

    /**
     * @param  iterable<ProvisioningServer>  $servers
     * @return list<int>
     */
    public function unconfiguredIds(iterable $servers): array
    {
        $ids = [];
        foreach ($servers as $server) {
            if (! $this->isConfigured($server)) {
                $ids[] = $server->id;
            }
        }

        return $ids;
    }

    private function resolve(ProvisioningServer $server): bool
    {
        $provisioner = $this->provisioners->get($server->provider_key);
        if ($provisioner === null) {
            return false;
        }

        if (! $provisioner instanceof ConfiguresProvisioningServers) {
            return $this->extensions->runtimeIsConfigured($provisioner);
        }

        try {
            // Decrypting the stored connection fails when the application key changed.
            $settings = $server->getAttribute('settings');
        } catch (Throwable) {
            return false;
        }
        $settings = is_array($settings) ? $settings : [];

        foreach ($provisioner->serverSettings() as $definition) {
            if ($definition->required && ! ExtensionSettingsRepository::valueIsFilled($settings[$definition->key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
