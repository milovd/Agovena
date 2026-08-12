<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;

final class CustomerAccountOverview
{
    /**
     * @var array<string, array{
     *     factory: callable(Customer): (AccountOverviewCard|null),
     *     sort: int
     * }>
     */
    private array $factories = [];

    /**
     * @param  callable(Customer): (AccountOverviewCard|null)  $factory
     */
    public function register(string $id, callable $factory, int $sort = 0): void
    {
        $this->factories[$id] = [
            'factory' => $factory,
            'sort' => $sort,
        ];
    }

    /**
     * @return list<AccountOverviewCard>
     */
    public function cardsFor(Customer $customer): array
    {
        $registered = $this->factories;
        uasort($registered, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        $cards = [];
        foreach ($registered as $entry) {
            $card = ($entry['factory'])($customer);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }
}
