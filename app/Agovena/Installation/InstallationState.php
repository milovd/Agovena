<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Authoritative installation lock for Agovena.
 *
 * Persistence strategy:
 * - Primary (authoritative): settings row system.installed_at (+ system.install_id)
 *   survives storage volume replacement / wipe as long as the database remains.
 * - Secondary (defense in depth): storage/app/agovena/installed.json with the same
 *   install_id. Never treated as installed on its own — avoids re-opening /install
 *   after a storage-only restore of an old lock file against a fresh database.
 *
 * markInstalled() is only called after owner + store setup succeed.
 */
final class InstallationState
{
    public const SETTINGS_GROUP = 'system';

    public const KEY_INSTALLED_AT = 'installed_at';

    public const KEY_INSTALL_ID = 'install_id';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function installed(): bool
    {
        return $this->installedAt() !== null;
    }

    public function notInstalled(): bool
    {
        return ! $this->installed();
    }

    public function installedAt(): ?string
    {
        try {
            $value = $this->settings->get(self::SETTINGS_GROUP, self::KEY_INSTALLED_AT);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function installId(): ?string
    {
        try {
            $value = $this->settings->get(self::SETTINGS_GROUP, self::KEY_INSTALL_ID);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function assertNotInstalled(): void
    {
        if ($this->installed()) {
            throw InstallationException::alreadyInstalled();
        }
    }

    public function markInstalled(): void
    {
        $this->assertNotInstalled();

        $installId = (string) Str::uuid();
        $installedAt = now()->toIso8601String();

        $this->settings->setMany(self::SETTINGS_GROUP, [
            self::KEY_INSTALLED_AT => $installedAt,
            self::KEY_INSTALL_ID => $installId,
        ]);

        $this->writeMarkerFile($installId, $installedAt);
    }

    /**
     * @return array{install_id: string, installed_at: string}|null
     */
    public function markerFile(): ?array
    {
        $path = $this->markerPath();
        if (! is_file($path)) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $id = $decoded['install_id'] ?? null;
        $at = $decoded['installed_at'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($at) || $at === '') {
            return null;
        }

        return ['install_id' => $id, 'installed_at' => $at];
    }

    public function markerPath(): string
    {
        return storage_path('app/agovena/installed.json');
    }

    private function writeMarkerFile(string $installId, string $installedAt): void
    {
        $dir = dirname($this->markerPath());
        File::ensureDirectoryExists($dir);

        File::put(
            $this->markerPath(),
            json_encode([
                'install_id' => $installId,
                'installed_at' => $installedAt,
                'app' => 'agovena',
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n",
        );
    }
}
