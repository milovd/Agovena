<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class PaymentInitiationResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $status,
        public ?string $externalId = null,
        public ?string $redirectUrl = null,
        public array $metadata = [],
        public ?string $message = null,
    ) {}

    public static function completed(?string $externalId = null, array $metadata = []): self
    {
        return new self(status: 'completed', externalId: $externalId, metadata: $metadata);
    }

    public static function redirect(string $url, ?string $externalId = null, array $metadata = []): self
    {
        return new self(status: 'redirect', externalId: $externalId, redirectUrl: $url, metadata: $metadata);
    }

    public static function pending(?string $externalId = null, array $metadata = [], ?string $message = null): self
    {
        return new self(status: 'pending', externalId: $externalId, metadata: $metadata, message: $message);
    }

    public static function failed(string $message, array $metadata = []): self
    {
        return new self(status: 'failed', metadata: $metadata, message: $message);
    }
}
