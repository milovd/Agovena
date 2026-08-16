<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Models\AgovenaModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Historical store preset id "digital" enabled the Downloads module (`digital`).
 * Merchant wording now splits Digital products (secrets) from Downloadable products.
 * Remap stored selections so upgrades keep the Downloads intent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasTable('agovena_modules')) {
            return;
        }

        /** @var SettingsRepository $settings */
        $settings = app(SettingsRepository::class);
        $selected = $settings->get('store', 'presets', []);
        if (! is_array($selected) || $selected === []) {
            return;
        }

        $selected = array_values(array_filter($selected, static fn ($id): bool => is_string($id) && $id !== ''));
        if (! in_array('digital', $selected, true)) {
            return;
        }

        $downloadsEnabled = AgovenaModule::query()
            ->where('module_id', 'digital')
            ->where('enabled', true)
            ->exists();

        if (! $downloadsEnabled) {
            return;
        }

        $selected = array_values(array_filter(
            $selected,
            static fn (string $id): bool => $id !== 'digital',
        ));

        if (! in_array('downloadable', $selected, true)) {
            $selected[] = 'downloadable';
        }

        $settings->set('store', 'presets', $selected);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        /** @var SettingsRepository $settings */
        $settings = app(SettingsRepository::class);
        $selected = $settings->get('store', 'presets', []);
        if (! is_array($selected) || $selected === []) {
            return;
        }

        $selected = array_values(array_filter($selected, static fn ($id): bool => is_string($id) && $id !== ''));
        if (! in_array('downloadable', $selected, true)) {
            return;
        }

        $selected = array_values(array_filter(
            $selected,
            static fn (string $id): bool => $id !== 'downloadable',
        ));

        if (! in_array('digital', $selected, true)) {
            $selected[] = 'digital';
        }

        $settings->set('store', 'presets', $selected);
    }
};
