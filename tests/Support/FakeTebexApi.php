<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Tebex\TebexApi;
use Agovena\Extensions\Tebex\TebexProviderException;

final class FakeTebexApi implements TebexApi
{
    public int $basketCalls = 0;

    public int $addPackageCalls = 0;

    /** @var list<string|null> */
    public array $addPackageIdempotencyKeys = [];

    /** @var array<string, int> */
    public array $packages = [];

    public ?string $throwOn = null;

    public function createBasket(array $payload, ?string $idempotencyKey = null): array
    {
        $this->basketCalls++;

        return [
            'id' => 'basket-test',
            'ident' => 'basket-ident',
            'links' => ['checkout' => 'https://checkout.tebex.test/basket-ident'],
        ];
    }

    public function getBasket(string $ident): array
    {
        if ($this->throwOn === 'get_basket_after_add' && $this->addPackageCalls > 0) {
            throw new TebexProviderException('tebex::messages.errors.request_failed', null, true);
        }

        return [
            'id' => 'basket-test',
            'ident' => $ident,
            'packages' => array_map(
                static fn (string $packageId, int $quantity): array => ['id' => $packageId, 'quantity' => $quantity],
                array_keys($this->packages),
                array_values($this->packages),
            ),
            'links' => ['checkout' => 'https://checkout.tebex.test/'.$ident],
        ];
    }

    public function addPackage(string $ident, string $packageId, int $quantity, ?string $idempotencyKey = null): array
    {
        if ($this->throwOn === 'add_package') {
            throw new TebexProviderException('tebex::messages.errors.request_failed', null, true);
        }

        $this->addPackageCalls++;
        $this->addPackageIdempotencyKeys[] = $idempotencyKey;
        $this->packages[$packageId] = ($this->packages[$packageId] ?? 0) + $quantity;

        return ['id' => 'basket-test', 'ident' => $ident, 'package_id' => $packageId, 'quantity' => $quantity];
    }

    public function getPayment(string $transactionId): array
    {
        return ['transaction_id' => $transactionId, 'status' => ['id' => 1]];
    }

    /** @var array<string, mixed>|null */
    public ?array $refund = null;

    /** @var list<string|null> */
    public array $refundIdempotencyKeys = [];

    public function refundPayment(string $transactionId, ?string $reason = null, ?string $idempotencyKey = null): array
    {
        $this->refundIdempotencyKeys[] = $idempotencyKey;
        if ($this->throwOn === 'refund') {
            throw new TebexProviderException('tebex::messages.errors.request_failed', null, true);
        }

        return $this->refund ?? ['id' => 'refund-test', 'transaction_id' => $transactionId];
    }
}
