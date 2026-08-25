<?php

declare(strict_types=1);

namespace App\Agovena\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsRepository
{
    private const CACHE_PREFIX = 'agovena.settings.';

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $encoded = $this->rememberEncoded($group, $key);

        if ($encoded === null && ! $this->exists($group, $key)) {
            return $default;
        }

        return $this->decode($encoded);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $encoded = $this->encode($value);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $encoded],
        );

        Cache::forever($this->cacheKey($group, $key), $encoded);
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

    private function exists(string $group, string $key): bool
    {
        return Setting::query()->where('group', $group)->where('key', $key)->exists();
    }

    /**
     * Cache only scalar encoded values - never Eloquent models (DB cache unserialize breaks them).
     */
    private function rememberEncoded(string $group, string $key): ?string
    {
        $cached = Cache::get($this->cacheKey($group, $key), new \stdClass);

        if (! $cached instanceof \stdClass) {
            if (is_object($cached)) {
                // Stale Eloquent/incomplete class entries from the previous cache strategy.
                Cache::forget($this->cacheKey($group, $key));
            } elseif (is_string($cached) || $cached === null) {
                return $cached;
            } else {
                Cache::forget($this->cacheKey($group, $key));
            }
        }

        $row = Setting::query()->where('group', $group)->where('key', $key)->first();
        $encoded = $row?->value;
        Cache::forever($this->cacheKey($group, $key), $encoded);

        return $encoded;
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
