<?php

declare(strict_types=1);

namespace App\Agovena\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class SettingsRepository
{
    private const CACHE_PREFIX = 'agovena.settings.';

    /** Cache markers: a cached null reads back as a cache miss, so absence needs its own value. */
    private const CACHE_MISSING = ['agovena_setting' => 'missing'];

    private const CACHE_EMPTY = ['agovena_setting' => 'empty'];

    /** Marks a stored value as an encrypted secret; values without it are legacy plain text. */
    public const SECRET_PREFIX = 'encrypted:';

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $cached = $this->cached($group, $key);

        if ($cached === self::CACHE_MISSING) {
            return $default;
        }

        return $this->decode($cached === self::CACHE_EMPTY ? null : $cached);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $encoded = $this->encode($value);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $encoded],
        );

        Cache::forever($this->cacheKey($group, $key), $encoded ?? self::CACHE_EMPTY);
    }

    /**
     * Store a secret encrypted with the application key. An empty value clears it.
     */
    public function setSecret(string $group, string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            $this->set($group, $key, null);

            return;
        }

        $this->set($group, $key, self::SECRET_PREFIX.Crypt::encryptString($value));
    }

    /**
     * Read a secret. Plain text stored before encryption existed is returned as is.
     */
    public function getSecret(string $group, string $key): ?string
    {
        $stored = $this->get($group, $key);
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        if (! str_starts_with($stored, self::SECRET_PREFIX)) {
            return $stored;
        }

        try {
            $value = Crypt::decryptString(substr($stored, strlen(self::SECRET_PREFIX)));
        } catch (DecryptException) {
            return null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($group, (string) $key, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function allInGroup(string $group): array
    {
        $rows = Setting::query()->where('group', $group)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $this->decode($row->value);
        }

        return $out;
    }

    public function forget(string $group, string $key): void
    {
        Cache::forget($this->cacheKey($group, $key));
    }

    /**
     * Cache only scalar encoded values or the plain array markers - never Eloquent models (DB cache
     * unserialize breaks them). A cached null reads back as a miss, so "no row" and "row without a
     * value" get explicit markers; otherwise every read of an unsaved setting queries and rewrites.
     *
     * @return string|array<string, string>
     */
    private function cached(string $group, string $key): string|array
    {
        $cacheKey = $this->cacheKey($group, $key);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) || $cached === self::CACHE_MISSING || $cached === self::CACHE_EMPTY) {
            return $cached;
        }

        if ($cached !== null) {
            // Stale Eloquent/incomplete class entries or other shapes from earlier cache strategies.
            Cache::forget($cacheKey);
        }

        $row = Setting::query()->where('group', $group)->where('key', $key)->first();
        $value = match (true) {
            $row === null => self::CACHE_MISSING,
            $row->value === null => self::CACHE_EMPTY,
            default => (string) $row->value,
        };
        Cache::forever($cacheKey, $value);

        return $value;
    }

    private function cacheKey(string $group, string $key): string
    {
        return self::CACHE_PREFIX.$group.'.'.$key;
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    private function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === '1' || $value === '0') {
            return $value;
        }

        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $value;
            }
        }

        return $value;
    }
}
