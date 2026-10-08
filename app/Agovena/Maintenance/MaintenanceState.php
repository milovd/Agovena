<?php

declare(strict_types=1);

namespace App\Agovena\Maintenance;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Stored storefront maintenance state. The message is plain text and must be
 * escaped wherever it is rendered.
 */
final readonly class MaintenanceState
{
    public function __construct(
        public bool $enabled,
        public ?string $message = null,
        public ?CarbonImmutable $endsAt = null,
        public ?CarbonImmutable $enabledAt = null,
    ) {}

    public static function off(): self
    {
        return new self(false);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $message = $data['message'] ?? null;

        return new self(
            enabled: filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            message: is_string($message) && trim($message) !== '' ? $message : null,
            endsAt: self::date($data['ends_at'] ?? null),
            enabledAt: self::date($data['enabled_at'] ?? null),
        );
    }

    /**
     * @return array{enabled: bool, message: string|null, ends_at: string|null, enabled_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'message' => $this->message,
            'ends_at' => $this->endsAt?->utc()->toIso8601String(),
            'enabled_at' => $this->enabledAt?->utc()->toIso8601String(),
        ];
    }

    /**
     * Seconds a client should wait before retrying: until the expected end
     * time when it lies in the future, otherwise the given default.
     */
    public function retryAfterSeconds(CarbonInterface $now, int $default): int
    {
        if ($this->endsAt === null || $this->endsAt->lessThanOrEqualTo($now)) {
            return $default;
        }

        return max(1, (int) ceil($now->diffInSeconds($this->endsAt, true)));
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
