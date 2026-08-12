<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

/**
 * Agovena-owned registrar for extra customer account nav links from Modules.
 */
final class CustomerAccountNav
{
    /** @var list<AccountNavItem> */
    private array $items = [];

    public function register(AccountNavItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return list<AccountNavItem>
     */
    public function items(): array
    {
        $items = $this->items;
        usort($items, static fn (AccountNavItem $a, AccountNavItem $b): int => $a->sort <=> $b->sort);

        return $items;
    }
}
