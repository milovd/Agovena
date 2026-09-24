<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $success,
        public ?string $externalRefundId = null,
        public ?string $message = null,
        public array $metadata = [],
        public bool $unknownOutcome = false,
        public bool $terminalFailure = false,
    ) {}

    public static function ok(?string $externalRefundId = null, array $metadata = []): self
    {
        return new self(success: true, externalRefundId: $externalRefundId, metadata: $metadata);
    }

    public static function fail(string $message, bool $terminalFailure = false): self
    {
        return new self(success: false, message: $message, terminalFailure: $terminalFailure);
    }

    public static function unknown(array $metadata = [], ?string $message = null): self
    {
        return new self(success: false, message: $message, metadata: $metadata, unknownOutcome: true);
    }
}
