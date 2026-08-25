<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class InstallationRequirements
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly EnsurePublicStorageLink $storageLink,
        private readonly ApplicationSchemaStatus $schema,
    ) {}

    /**
     * @return list<RequirementCheck>
     */
    public function checks(): array
    {
        return [
            $this->check('php_version', 'installer.checks.php_version', version_compare(PHP_VERSION, '8.3.0', '>='), true, 'PHP '.PHP_VERSION),
            $this->check('ext_openssl', 'installer.checks.ext_openssl', extension_loaded('openssl')),
            $this->check('ext_pdo', 'installer.checks.ext_pdo', extension_loaded('pdo')),
            $this->check('ext_mbstring', 'installer.checks.ext_mbstring', extension_loaded('mbstring')),
            $this->check('ext_tokenizer', 'installer.checks.ext_tokenizer', extension_loaded('tokenizer')),
            $this->check('ext_xml', 'installer.checks.ext_xml', extension_loaded('xml')),
            $this->check('ext_ctype', 'installer.checks.ext_ctype', extension_loaded('ctype')),
            $this->check('ext_json', 'installer.checks.ext_json', extension_loaded('json')),
            $this->check('ext_fileinfo', 'installer.checks.ext_fileinfo', extension_loaded('fileinfo')),
            $this->check('app_key', 'installer.checks.app_key', filled(config('app.key'))),
            $this->check('storage_writable', 'installer.checks.storage_writable', is_writable(storage_path())),
            $this->check('bootstrap_cache_writable', 'installer.checks.bootstrap_cache_writable', is_writable(base_path('bootstrap/cache'))),
            $this->databaseConnectionCheck(),
            $this->migrationsCheck(),
            $this->storageLinkCheck(),
            $this->themesCheck(),
        ];
    }

    public function ready(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check->required && ! $check->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<RequirementCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks(),
            static fn (RequirementCheck $check): bool => $check->required && ! $check->passed,
        ));
    }

    private function databaseConnectionCheck(): RequirementCheck
    {
        try {
            DB::connection()->getPdo();

            return $this->check('database', 'installer.checks.database', true, true, (string) config('database.default'));
        } catch (Throwable $e) {
            return $this->check('database', 'installer.checks.database', false, true, $e->getMessage());
        }
    }

    private function migrationsCheck(): RequirementCheck
    {
        try {
            $pending = $this->schema->pending();
            $current = $pending === [];
            $detail = $current
                ? null
                : count($pending).' pending ('.implode(', ', $pending).') - php artisan agovena:upgrade';

            return $this->check(
                'migrations',
                'installer.checks.migrations',
                $current,
                true,
                $detail,
            );
        } catch (Throwable $e) {
            return $this->check('migrations', 'installer.checks.migrations', false, true, $e->getMessage());
        }
    }

    private function storageLinkCheck(): RequirementCheck
    {
        $ok = $this->storageLink->ensure();

        if ($ok) {
            return $this->check(
                'storage_link',
                'installer.checks.storage_link',
                true,
                false,
                null,
                'public/storage → storage/app/public',
            );
        }

        return $this->check(
            'storage_link',
            'installer.checks.storage_link',
            false,
            false,
            (string) __('installer.checks.storage_link_message'),
            (string) __('installer.checks.storage_link_technical'),
        );
    }

    private function themesCheck(): RequirementCheck
    {
        try {
            $themes = $this->themes->all();
            $ok = $themes !== [];

            return $this->check(
                'themes',
                'installer.checks.themes',
                $ok,
                true,
                $ok ? implode(', ', array_keys($themes)) : 'No Themes found under themes/',
            );
        } catch (Throwable $e) {
            return $this->check('themes', 'installer.checks.themes', false, true, $e->getMessage());
        }
    }

    private function check(
        string $id,
        string $label,
        bool $passed,
        bool $required = true,
        ?string $detail = null,
        ?string $technicalDetail = null,
    ): RequirementCheck {
        return new RequirementCheck($id, $label, $passed, $required, $detail, $technicalDetail);
    }
}
