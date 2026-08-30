<?php

declare(strict_types=1);

namespace App\Models;

use App\Agovena\Security\SensitiveDataRedactor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * @property int $id
 * @property int $product_id
 * @property string $capability
 * @property array<string, mixed>|null $config
 */
#[Fillable(['product_id', 'capability', 'config'])]
class ProductCapability extends Model
{
    protected $hidden = ['config', 'config_encrypted'];

    protected function casts(): array
    {
        return [];
    }

    protected function config(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                $config = is_array($decoded) ? $decoded : null;

                return self::sanitizePublicConfig($config ?? []);
            },
            set: function (mixed $value): array {
                if ($value === null) {
                    return ['config' => null, 'config_encrypted' => null];
                }
                if (! is_array($value)) {
                    throw new \InvalidArgumentException('Product capability config must be an array.');
                }

                $existing = $this->runtimeConfig() ?? [];
                $value = self::restoreRedactedSecrets($value, $existing);

                return [
                    'config' => json_encode(self::sanitizePublicConfig($value), JSON_THROW_ON_ERROR),
                    'config_encrypted' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)),
                ];
            },
        );
    }

    /** @return array<string, mixed>|null */
    public function runtimeConfig(): ?array
    {
        $encrypted = $this->attributes['config_encrypted'] ?? null;
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return self::isValidRuntimeConfig($decoded) ? $decoded : null;
    }

    public function hasCorruptConfig(): bool
    {
        $encrypted = $this->attributes['config_encrypted'] ?? null;
        if (is_string($encrypted) && trim($encrypted) !== '') {
            try {
                $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return true;
            }

            return ! self::isValidRuntimeConfig($decoded);
        }

        $raw = $this->attributes['config'] ?? null;
        if ($raw === null || $raw === '') {
            return false;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return ! self::isValidRuntimeConfig($decoded);
    }

    private static function isValidRuntimeConfig(mixed $value): bool
    {
        return is_array($value)
            && ($value === [] || ! array_is_list($value))
            && ! array_key_exists('_migration_status', $value);
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private static function sanitizePublicConfig(array $value): array
    {
        $sanitized = SensitiveDataRedactor::redact($value);

        return is_array($sanitized) ? $sanitized : [];
    }

    /** @param array<string, mixed> $value @param array<string, mixed> $existing @return array<string, mixed> */
    private static function restoreRedactedSecrets(array $value, array $existing): array
    {
        foreach ($value as $key => $current) {
            if ($current === '[REDACTED]' && array_key_exists($key, $existing)) {
                $value[$key] = $existing[$key];

                continue;
            }
            if (is_array($current) && is_array($existing[$key] ?? null)) {
                $value[$key] = self::restoreRedactedSecrets($current, $existing[$key]);
            }
        }

        return $value;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
