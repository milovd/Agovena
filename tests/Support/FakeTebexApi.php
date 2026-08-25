<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Tebex\TebexApi;

final class FakeTebexApi implements TebexApi
{
    public int $basketCalls = 0;

    public function createBasket(array $payload): array
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
        return [
            'id' => 'basket-test',
            'ident' => $ident,
            'links' => ['checkout' => 'https://checkout.tebex.test/'.$ident],
        ];
    }

    public function addPackage(string $ident, string $packageId, int $quantity): array
    {
        return ['id' => 'basket-test', 'ident' => $ident, 'package_id' => $packageId, 'quantity' => $quantity];
    }

    public function getPayment(string $transactionId): array
    {
        return ['transaction_id' => $transactionId, 'status' => ['id' => 1]];
    }

    public function refundPayment(string $transactionId, ?string $reason = null): array
    {
        return ['id' => 'refund-test', 'transaction_id' => $transactionId];
    }
}
