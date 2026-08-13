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
        $items = array_values(array_filter(
            $this->items,
            static function (AccountNavItem $item): bool {
                if (! is_callable($item->visible)) {
                    return true;
                }

                return (bool) ($item->visible)();
            },
        ));
        usort($items, static fn (AccountNavItem $a, AccountNavItem $b): int => $a->sort <=> $b->sort);

        return $items;
    }
}
