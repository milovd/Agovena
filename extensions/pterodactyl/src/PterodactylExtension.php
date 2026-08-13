<?php

declare(strict_types=1);

namespace Agovena\Extensions\Pterodactyl;

use App\Agovena\Extensions\Contracts\Extension;
use App\Agovena\Extensions\ExtensionContext;
use App\Agovena\Extensions\ExtensionSettingDefinition;

final class PterodactylExtension implements Extension
{
    public function id(): string
    {
        return 'pterodactyl';
    }

    public function register(ExtensionContext $context): void
    {
        $context->setting(new ExtensionSettingDefinition(
            key: 'panel_url',
            label: 'pterodactyl::messages.settings.panel_url',
            type: 'string',
            required: true,
            help: 'pterodactyl::messages.settings.panel_url_help',
        ));
        $context->setting(new ExtensionSettingDefinition(
            key: 'application_api_key',
            label: 'pterodactyl::messages.settings.application_api_key',
            type: 'string',
            secret: true,
            required: true,
            help: 'pterodactyl::messages.settings.application_api_key_help',
        ));
        $context->setting(new ExtensionSettingDefinition(
            key: 'client_api_key',
            label: 'pterodactyl::messages.settings.client_api_key',
            type: 'string',
            secret: true,
            help: 'pterodactyl::messages.settings.client_api_key_help',
        ));
        $context->setting(new ExtensionSettingDefinition(
            key: 'user_id',
            label: 'pterodactyl::messages.settings.user_id',
            type: 'string',
            required: true,
            help: 'pterodactyl::messages.settings.user_id_help',
        ));
        $context->setting(new ExtensionSettingDefinition(
            key: 'verify_tls',
            label: 'pterodactyl::messages.settings.verify_tls',
            type: 'boolean',
            default: true,
            help: 'pterodactyl::messages.settings.verify_tls_help',
        ));
        $context->setting(new ExtensionSettingDefinition(
            key: 'timeout',
            label: 'pterodactyl::messages.settings.timeout',
            type: 'string',
            default: '15',
            help: 'pterodactyl::messages.settings.timeout_help',
        ));

        $context->provisioner(app(PterodactylProvisioner::class));
        $context->health(static fn () => app(PterodactylProvisioner::class)->health());
    }
}
